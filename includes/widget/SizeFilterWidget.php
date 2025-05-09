<?php

namespace MediaWiki\Widget;

use OOUI\LabelWidget;
use OOUI\RadioSelectInputWidget;
use OOUI\TextInputWidget;
use OOUI\Widget;

/**
 * Select and input widget.
 *
 * @copyright 2011-2018 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license MIT
 */
class SizeFilterWidget extends Widget {
	/** @var array */
	protected $config;
	/** @var LabelWidget */
	protected $label;
	/** @var RadioSelectInputWidget */
	protected $radioselectinput;
	/** @var TextInputWidget */
	protected $textinput;

	/**
	 * RadioSelectInputWidget and a TextInputWidget to set minimum or maximum byte size
	 *
	 * @param array $config Configuration options
	 *   - array $config['textinput'] Configuration for the TextInputWidget
	 *   - array $config['radioselectinput'] Configuration for the RadioSelectWidget
	 *   - bool $config['selectMin'] Whether to select 'min', false would select 'max'
	 */
	public function __construct( array $config = [] ) {
		// Configuration initialization
		$config = array_merge( [
			'selectMin' => true,
			'textinput' => [],
			'radioselectinput' => []
		], $config );
		$config['textinput'] = array_merge( [
			'type' => 'number'
		], $config['textinput'] );
		$config['radioselectinput'] = array_merge( [ 'options' => [
			[
				'data' => 'min',
				'label' => wfMessage( 'minimum-size' )->text()
			],
			[
				'data' => 'max',
				'label' => wfMessage( 'maximum-size' )->text()
			]
		] ], $config['radioselectinput'] );

		// Parent constructor
		parent::__construct( $config );

		// Properties
		$this->config = $config;
		$this->radioselectinput = new RadioSelectInputWidget( $config[ 'radioselectinput'] );
		$this->textinput = new TextInputWidget( $config[ 'textinput' ] );
		$this->label = new LabelWidget( [ 'label' => wfMessage( 'pagesize' )->text() ] );

		// Initialization
		$this->radioselectinput->setValue( $config[ 'selectMin' ] ? 'min' : 'max' );
		$this
			->addClasses( [ 'mw-widget-sizeFilterWidget' ] )
			->appendContent( $this->radioselectinput, $this->textinput, $this->label );
	}

	/** @inheritDoc */
	protected function getJavaScriptClassName() {
		return 'mw.widgets.SizeFilterWidget';
	}

	/** @inheritDoc */
	public function getConfig( &$config ) {
		$config['textinput'] = $this->config['textinput'];
		$config['radioselectinput'] = $this->config['radioselectinput'];
		$config['selectMin'] = $this->config['selectMin'];
		return parent::getConfig( $config );
	}
}
