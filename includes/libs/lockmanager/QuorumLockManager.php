<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * Base class for lock managers that use a quorum of peer servers for locks.
 *
 * The resource space can also be sharded into separate peer groups.
 *
 * See MemcLockManager and RedisLockManager.
 *
 * @stable to extend
 * @ingroup LockManager
 * @since 1.20
 */
abstract class QuorumLockManager extends LockManager {
	/** @var array Map of bucket indexes to peer server lists */
	protected $srvsByBucket = []; // (bucket index => (lsrv1, lsrv2, ...))

	/** @var array Map of degraded buckets */
	protected $degradedBuckets = []; // (bucket index => UNIX timestamp)

	/** @inheritDoc */
	final protected function doLockByType( array $pathsByType ) {
		$status = StatusValue::newGood();

		$pathsByTypeByBucket = []; // (bucket => type => paths)
		// Get locks that need to be acquired (buckets => locks)...
		foreach ( $pathsByType as $type => $paths ) {
			foreach ( $paths as $path ) {
				if ( isset( $this->locksHeld[$path][$type] ) ) {
					++$this->locksHeld[$path][$type];
				} else {
					$bucket = $this->getBucketFromPath( $path );
					$pathsByTypeByBucket[$bucket][$type][] = $path;
				}
			}
		}

		// Acquire locks in each bucket in bucket order to reduce contention. Any blocking
		// mutexes during the acquisition step will not involve circular waiting on buckets.
		ksort( $pathsByTypeByBucket );

		$lockedPaths = []; // files locked in this attempt (type => paths)
		// Attempt to acquire these locks...
		foreach ( $pathsByTypeByBucket as $bucket => $bucketPathsByType ) {
			// Try to acquire the locks for this bucket
			$status->merge( $this->doLockingRequestBucket( $bucket, $bucketPathsByType ) );
			if ( !$status->isOK() ) {
				$status->merge( $this->doUnlockByType( $lockedPaths ) );

				return $status;
			}
			// Record these locks as active
			foreach ( $bucketPathsByType as $type => $paths ) {
				foreach ( $paths as $path ) {
					$this->locksHeld[$path][$type] = 1; // locked
					// Keep track of what locks were made in this attempt
					$lockedPaths[$type][] = $path;
				}
			}
		}

		return $status;
	}

	/**
	 * @stable to override
	 *
	 * @param array $pathsByType
	 *
	 * @return StatusValue
	 */
	protected function doUnlockByType( array $pathsByType ) {
		$status = StatusValue::newGood();

		$pathsByTypeByBucket = []; // (bucket => type => paths)
		foreach ( $pathsByType as $type => $paths ) {
			foreach ( $paths as $path ) {
				if ( !isset( $this->locksHeld[$path][$type] ) ) {
					$status->warning( 'lockmanager-notlocked', $path );
				} else {
					--$this->locksHeld[$path][$type];
					// Reference count the locks held and release locks when zero
					if ( $this->locksHeld[$path][$type] <= 0 ) {
						unset( $this->locksHeld[$path][$type] );
						$bucket = $this->getBucketFromPath( $path );
						$pathsByTypeByBucket[$bucket][$type][] = $path;
					}
					if ( $this->locksHeld[$path] === [] ) {
						unset( $this->locksHeld[$path] ); // no SH or EX locks left for key
					}
				}
			}
		}

		// Remove these specific locks if possible, or at least release
		// all locks once this process is currently not holding any locks.
		foreach ( $pathsByTypeByBucket as $bucket => $bucketPathsByType ) {
			$status->merge( $this->doUnlockingRequestBucket( $bucket, $bucketPathsByType ) );
		}
		if ( $this->locksHeld === [] ) {
			$status->merge( $this->releaseAllLocks() );
			$this->degradedBuckets = []; // safe to retry the normal quorum
		}

		return $status;
	}

	/**
	 * Attempt to acquire locks with the peers for a bucket.
	 * This is all or nothing; if any key is locked then this totally fails.
	 *
	 * @param int $bucket
	 * @param array $pathsByType Map of LockManager::LOCK_* constants to lists of paths
	 * @return StatusValue
	 */
	final protected function doLockingRequestBucket( $bucket, array $pathsByType ) {
		return $this->collectPledgeQuorum(
			$bucket,
			function ( $lockSrv ) use ( $pathsByType ) {
				return $this->getLocksOnServer( $lockSrv, $pathsByType );
			}
		);
	}

	/**
	 * Attempt to release locks with the peers for a bucket
	 *
	 * @param int $bucket
	 * @param array $pathsByType Map of LockManager::LOCK_* constants to lists of paths
	 * @return StatusValue
	 */
	final protected function doUnlockingRequestBucket( $bucket, array $pathsByType ) {
		return $this->releasePledges(
			$bucket,
			function ( $lockSrv ) use ( $pathsByType ) {
				return $this->freeLocksOnServer( $lockSrv, $pathsByType );
			}
		);
	}

	/**
	 * Attempt to acquire pledges with the peers for a bucket.
	 * This is all or nothing; if any key is already pledged then this totally fails.
	 *
	 * @param int $bucket
	 * @param callable $callback Pledge method taking a server name and yielding a StatusValue
	 * @return StatusValue
	 */
	final protected function collectPledgeQuorum( $bucket, callable $callback ) {
		$status = StatusValue::newGood();

		$yesVotes = 0; // locks made on trustable servers
		$votesLeft = count( $this->srvsByBucket[$bucket] ); // remaining peers
		$quorum = floor( $votesLeft / 2 + 1 ); // simple majority
		// Get votes for each peer, in order, until we have enough...
		foreach ( $this->srvsByBucket[$bucket] as $lockSrv ) {
			if ( !$this->isServerUp( $lockSrv ) ) {
				--$votesLeft;
				$status->warning( 'lockmanager-fail-svr-acquire', $lockSrv );
				$this->degradedBuckets[$bucket] = time();
				continue; // server down?
			}
			// Attempt to acquire the lock on this peer
			$status->merge( $callback( $lockSrv ) );
			if ( !$status->isOK() ) {
				return $status; // vetoed; resource locked
			}
			++$yesVotes; // success for this peer
			if ( $yesVotes >= $quorum ) {
				return $status; // lock obtained
			}
			--$votesLeft;
			$votesNeeded = $quorum - $yesVotes;
			if ( $votesNeeded > $votesLeft ) {
				break; // short-circuit
			}
		}
		// At this point, we must not have met the quorum
		$status->setResult( false );

		return $status;
	}

	/**
	 * Attempt to release pledges with the peers for a bucket
	 *
	 * @param int $bucket
	 * @param callable $callback Pledge method taking a server name and yielding a StatusValue
	 * @return StatusValue
	 */
	final protected function releasePledges( $bucket, callable $callback ) {
		$status = StatusValue::newGood();

		$yesVotes = 0; // locks freed on trustable servers
		$votesLeft = count( $this->srvsByBucket[$bucket] ); // remaining peers
		$quorum = floor( $votesLeft / 2 + 1 ); // simple majority
		$isDegraded = isset( $this->degradedBuckets[$bucket] ); // not the normal quorum?
		foreach ( $this->srvsByBucket[$bucket] as $lockSrv ) {
			if ( !$this->isServerUp( $lockSrv ) ) {
				$status->warning( 'lockmanager-fail-svr-release', $lockSrv );
			} else {
				// Attempt to release the lock on this peer
				$status->merge( $callback( $lockSrv ) );
				++$yesVotes; // success for this peer
				// Normally the first peers form the quorum, and the others are ignored.
				// Ignore them in this case, but not when an alternative quorum was used.
				if ( $yesVotes >= $quorum && !$isDegraded ) {
					break; // lock released
				}
			}
		}
		// Set a bad StatusValue if the quorum was not met.
		// Assumes the same "up" servers as during the acquire step.
		$status->setResult( $yesVotes >= $quorum );

		return $status;
	}

	/**
	 * Get the bucket for resource path.
	 * This should avoid throwing any exceptions.
	 *
	 * @param string $path
	 * @return int
	 */
	protected function getBucketFromPath( $path ) {
		$prefix = substr( sha1( $path ), 0, 2 ); // first 2 hex chars (8 bits)
		return (int)base_convert( $prefix, 16, 10 ) % count( $this->srvsByBucket );
	}

	/**
	 * Check if a lock server is up.
	 * This should process cache results to reduce RTT.
	 *
	 * @param string $lockSrv
	 * @return bool
	 */
	abstract protected function isServerUp( $lockSrv );

	/**
	 * Get a connection to a lock server and acquire locks
	 *
	 * @param string $lockSrv
	 * @param array $pathsByType Map of LockManager::LOCK_* constants to lists of paths
	 * @return StatusValue
	 */
	abstract protected function getLocksOnServer( $lockSrv, array $pathsByType );

	/**
	 * Get a connection to a lock server and release locks on $paths.
	 *
	 * Subclasses must effectively implement this or releaseAllLocks().
	 *
	 * @param string $lockSrv
	 * @param array $pathsByType Map of LockManager::LOCK_* constants to lists of paths
	 * @return StatusValue
	 */
	abstract protected function freeLocksOnServer( $lockSrv, array $pathsByType );

	/**
	 * Release all locks that this session is holding.
	 *
	 * Subclasses must effectively implement this or freeLocksOnServer().
	 *
	 * @return StatusValue
	 */
	abstract protected function releaseAllLocks();

	/** @inheritDoc */
	final protected function doLock( array $paths, $type ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new LogicException( __METHOD__ . ': proxy class does not need this method.' );
	}

	/** @inheritDoc */
	final protected function doUnlock( array $paths, $type ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new LogicException( __METHOD__ . ': proxy class does not need this method.' );
	}
}
