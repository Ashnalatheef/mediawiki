"use strict";var zt=Object.defineProperty,It=Object.defineProperties;var jt=Object.getOwnPropertyDescriptors;var tt=Object.getOwnPropertySymbols;var yt=Object.prototype.hasOwnProperty,xt=Object.prototype.propertyIsEnumerable;var wt=(t,e,n)=>e in t?zt(t,e,{enumerable:!0,configurable:!0,writable:!0,value:n}):t[e]=n,C=(t,e)=>{for(var n in e||(e={}))yt.call(e,n)&&wt(t,n,e[n]);if(tt)for(var n of tt(e))xt.call(e,n)&&wt(t,n,e[n]);return t},E=(t,e)=>It(t,jt(e));var et=(t,e)=>{var n={};for(var o in t)yt.call(t,o)&&e.indexOf(o)<0&&(n[o]=t[o]);if(t!=null&&tt)for(var o of tt(t))e.indexOf(o)<0&&xt.call(t,o)&&(n[o]=t[o]);return n};var B=(t,e,n)=>new Promise((o,i)=>{var s=c=>{try{a(n.next(c))}catch(l){i(l)}},r=c=>{try{a(n.throw(c))}catch(l){i(l)}},a=c=>c.done?o(c.value):Promise.resolve(c.value).then(s,r);a((n=n.apply(t,e)).next())});const v=require("vue"),Xt=["top","right","bottom","left"],K=Math.min,T=Math.max,ot=Math.round,nt=Math.floor,P=t=>({x:t,y:t}),Yt={left:"right",right:"left",bottom:"top",top:"bottom"},qt={start:"end",end:"start"};function J(t,e){return typeof t=="function"?t(e):t}function N(t){return t.split("-")[0]}function Q(t){return t.split("-")[1]}function Ut(t){return t==="x"?"y":"x"}function Mt(t){return t==="y"?"height":"width"}function lt(t){return["top","bottom"].includes(N(t))?"y":"x"}function Ft(t){return Ut(lt(t))}function Kt(t,e,n){n===void 0&&(n=!1);const o=Q(t),i=Ft(t),s=Mt(i);let r=i==="x"?o===(n?"end":"start")?"right":"left":o==="start"?"bottom":"top";return e.reference[s]>e.floating[s]&&(r=it(r)),[r,it(r)]}function Gt(t){const e=it(t);return[ft(t),e,ft(e)]}function ft(t){return t.replace(/start|end/g,e=>qt[e])}function Jt(t,e,n){const o=["left","right"],i=["right","left"],s=["top","bottom"],r=["bottom","top"];switch(t){case"top":case"bottom":return n?e?i:o:e?o:i;case"left":case"right":return e?s:r;default:return[]}}function Qt(t,e,n,o){const i=Q(t);let s=Jt(N(t),n==="start",o);return i&&(s=s.map(r=>r+"-"+i),e&&(s=s.concat(s.map(ft)))),s}function it(t){return t.replace(/left|right|bottom|top/g,e=>Yt[e])}function Zt(t){return C({top:0,right:0,bottom:0,left:0},t)}function te(t){return typeof t!="number"?Zt(t):{top:t,right:t,bottom:t,left:t}}function rt(t){return E(C({},t),{top:t.y,left:t.x,right:t.x+t.width,bottom:t.y+t.height})}function bt(t,e,n){let{reference:o,floating:i}=t;const s=lt(e),r=Ft(e),a=Mt(r),c=N(e),l=s==="y",u=o.x+o.width/2-i.width/2,d=o.y+o.height/2-i.height/2,m=o[a]/2-i[a]/2;let f;switch(c){case"top":f={x:u,y:o.y-i.height};break;case"bottom":f={x:u,y:o.y+o.height};break;case"right":f={x:o.x+o.width,y:d};break;case"left":f={x:o.x-i.width,y:d};break;default:f={x:o.x,y:o.y}}switch(Q(e)){case"start":f[r]-=m*(n&&l?-1:1);break;case"end":f[r]+=m*(n&&l?-1:1);break}return f}const ee=(t,e,n)=>B(exports,null,function*(){const{placement:o="bottom",strategy:i="absolute",middleware:s=[],platform:r}=n,a=s.filter(Boolean),c=yield r.isRTL==null?void 0:r.isRTL(e);let l=yield r.getElementRects({reference:t,floating:e,strategy:i}),{x:u,y:d}=bt(l,o,c),m=o,f={},p=0;for(let h=0;h<a.length;h++){const{name:w,fn:g}=a[h],{x:y,y:x,data:O,reset:R}=yield g({x:u,y:d,initialPlacement:o,placement:m,strategy:i,middlewareData:f,rects:l,platform:r,elements:{reference:t,floating:e}});u=y!=null?y:u,d=x!=null?x:d,f=E(C({},f),{[w]:C(C({},f[w]),O)}),R&&p<=50&&(p++,typeof R=="object"&&(R.placement&&(m=R.placement),R.rects&&(l=R.rects===!0?yield r.getElementRects({reference:t,floating:e,strategy:i}):R.rects),{x:u,y:d}=bt(l,m,c)),h=-1)}return{x:u,y:d,placement:m,strategy:i,middlewareData:f}});function st(t,e){return B(this,null,function*(){var n;e===void 0&&(e={});const{x:o,y:i,platform:s,rects:r,elements:a,strategy:c}=t,{boundary:l="clippingAncestors",rootBoundary:u="viewport",elementContext:d="floating",altBoundary:m=!1,padding:f=0}=J(e,t),p=te(f),w=a[m?d==="floating"?"reference":"floating":d],g=rt(yield s.getClippingRect({element:(n=yield s.isElement==null?void 0:s.isElement(w))==null||n?w:w.contextElement||(yield s.getDocumentElement==null?void 0:s.getDocumentElement(a.floating)),boundary:l,rootBoundary:u,strategy:c})),y=d==="floating"?E(C({},r.floating),{x:o,y:i}):r.reference,x=yield s.getOffsetParent==null?void 0:s.getOffsetParent(a.floating),O=(yield s.isElement==null?void 0:s.isElement(x))?(yield s.getScale==null?void 0:s.getScale(x))||{x:1,y:1}:{x:1,y:1},R=rt(s.convertOffsetParentRelativeRectToViewportRelativeRect?yield s.convertOffsetParentRelativeRectToViewportRelativeRect({elements:a,rect:y,offsetParent:x,strategy:c}):y);return{top:(g.top-R.top+p.top)/O.y,bottom:(R.bottom-g.bottom+p.bottom)/O.y,left:(g.left-R.left+p.left)/O.x,right:(R.right-g.right+p.right)/O.x}})}const ne=function(t){return t===void 0&&(t={}),{name:"flip",options:t,fn(n){return B(this,null,function*(){var o,i;const{placement:s,middlewareData:r,rects:a,initialPlacement:c,platform:l,elements:u}=n,gt=J(t,n),{mainAxis:d=!0,crossAxis:m=!0,fallbackPlacements:f,fallbackStrategy:p="bestFit",fallbackAxisSideDirection:h="none",flipAlignment:w=!0}=gt,g=et(gt,["mainAxis","crossAxis","fallbackPlacements","fallbackStrategy","fallbackAxisSideDirection","flipAlignment"]);if((o=r.arrow)!=null&&o.alignmentOffset)return{};const y=N(s),x=N(c)===c,O=yield l.isRTL==null?void 0:l.isRTL(u.floating),R=f||(x||!w?[it(c)]:Gt(c));!f&&h!=="none"&&R.push(...Qt(c,w,h,O));const M=[c,...R],b=yield st(n,g),A=[];let S=((i=r.flip)==null?void 0:i.overflows)||[];if(d&&A.push(b[y]),m){const W=Kt(s,a,O);A.push(b[W[0]],b[W[1]])}if(S=[...S,{placement:s,overflows:A}],!A.every(W=>W<=0)){var X,Y;const W=(((X=r.flip)==null?void 0:X.index)||0)+1,vt=M[W];if(vt)return{data:{index:W,overflows:S},reset:{placement:vt}};let q=(Y=S.filter(_=>_.overflows[0]<=0).sort((_,z)=>_.overflows[1]-z.overflows[1])[0])==null?void 0:Y.placement;if(!q)switch(p){case"bestFit":{var ht;const _=(ht=S.map(z=>[z.placement,z.overflows.filter(U=>U>0).reduce((U,_t)=>U+_t,0)]).sort((z,U)=>z[1]-U[1])[0])==null?void 0:ht[0];_&&(q=_);break}case"initialPlacement":q=c;break}if(s!==q)return{reset:{placement:q}}}return{}})}}};function Rt(t,e){return{top:t.top-e.height,right:t.right-e.width,bottom:t.bottom-e.height,left:t.left-e.width}}function Ot(t){return Xt.some(e=>t[e]>=0)}const oe=function(t){return t===void 0&&(t={}),{name:"hide",options:t,fn(n){return B(this,null,function*(){const{rects:o}=n,r=J(t,n),{strategy:i="referenceHidden"}=r,s=et(r,["strategy"]);switch(i){case"referenceHidden":{const a=yield st(n,E(C({},s),{elementContext:"reference"})),c=Rt(a,o.reference);return{data:{referenceHiddenOffsets:c,referenceHidden:Ot(c)}}}case"escaped":{const a=yield st(n,E(C({},s),{altBoundary:!0})),c=Rt(a,o.floating);return{data:{escapedOffsets:c,escaped:Ot(c)}}}default:return{}}})}}};function ie(t,e){return B(this,null,function*(){const{placement:n,platform:o,elements:i}=t,s=yield o.isRTL==null?void 0:o.isRTL(i.floating),r=N(n),a=Q(n),c=lt(n)==="y",l=["left","top"].includes(r)?-1:1,u=s&&c?-1:1,d=J(e,t);let{mainAxis:m,crossAxis:f,alignmentAxis:p}=typeof d=="number"?{mainAxis:d,crossAxis:0,alignmentAxis:null}:C({mainAxis:0,crossAxis:0,alignmentAxis:null},d);return a&&typeof p=="number"&&(f=a==="end"?p*-1:p),c?{x:f*u,y:m*l}:{x:m*l,y:f*u}})}const re=function(t){return t===void 0&&(t=0),{name:"offset",options:t,fn(n){return B(this,null,function*(){var o,i;const{x:s,y:r,placement:a,middlewareData:c}=n,l=yield ie(n,t);return a===((o=c.offset)==null?void 0:o.placement)&&(i=c.arrow)!=null&&i.alignmentOffset?{}:{x:s+l.x,y:r+l.y,data:E(C({},l),{placement:a})}})}}},se=function(t){return t===void 0&&(t={}),{name:"size",options:t,fn(n){return B(this,null,function*(){const{placement:o,rects:i,platform:s,elements:r}=n,b=J(t,n),{apply:a=()=>{}}=b,c=et(b,["apply"]),l=yield st(n,c),u=N(o),d=Q(o),m=lt(o)==="y",{width:f,height:p}=i.floating;let h,w;u==="top"||u==="bottom"?(h=u,w=d===((yield s.isRTL==null?void 0:s.isRTL(r.floating))?"start":"end")?"left":"right"):(w=u,h=d==="end"?"top":"bottom");const g=p-l[h],y=f-l[w],x=!n.middlewareData.shift;let O=g,R=y;if(m){const A=f-l.left-l.right;R=d||x?K(y,A):A}else{const A=p-l.top-l.bottom;O=d||x?K(g,A):A}if(x&&!d){const A=T(l.left,0),S=T(l.right,0),X=T(l.top,0),Y=T(l.bottom,0);m?R=f-2*(A!==0||S!==0?A+S:T(l.left,l.right)):O=p-2*(X!==0||Y!==0?X+Y:T(l.top,l.bottom))}yield a(E(C({},n),{availableWidth:R,availableHeight:O}));const M=yield s.getDimensions(r.floating);return f!==M.width||p!==M.height?{reset:{rects:!0}}:{}})}}};function H(t){return ut(t)?(t.nodeName||"").toLowerCase():"#document"}function L(t){var e;return(t==null||(e=t.ownerDocument)==null?void 0:e.defaultView)||window}function k(t){var e;return(e=(ut(t)?t.ownerDocument:t.document)||window.document)==null?void 0:e.documentElement}function ut(t){return t instanceof Node||t instanceof L(t).Node}function $(t){return t instanceof Element||t instanceof L(t).Element}function F(t){return t instanceof HTMLElement||t instanceof L(t).HTMLElement}function Ct(t){return typeof ShadowRoot=="undefined"?!1:t instanceof ShadowRoot||t instanceof L(t).ShadowRoot}function Z(t){const{overflow:e,overflowX:n,overflowY:o,display:i}=D(t);return/auto|scroll|overlay|hidden|clip/.test(e+o+n)&&!["inline","contents"].includes(i)}function le(t){return["table","td","th"].includes(H(t))}function dt(t){const e=mt(),n=D(t);return n.transform!=="none"||n.perspective!=="none"||(n.containerType?n.containerType!=="normal":!1)||!e&&(n.backdropFilter?n.backdropFilter!=="none":!1)||!e&&(n.filter?n.filter!=="none":!1)||["transform","perspective","filter"].some(o=>(n.willChange||"").includes(o))||["paint","layout","strict","content"].some(o=>(n.contain||"").includes(o))}function ce(t){let e=j(t);for(;F(e)&&!ct(e);){if(dt(e))return e;e=j(e)}return null}function mt(){return typeof CSS=="undefined"||!CSS.supports?!1:CSS.supports("-webkit-backdrop-filter","none")}function ct(t){return["html","body","#document"].includes(H(t))}function D(t){return L(t).getComputedStyle(t)}function at(t){return $(t)?{scrollLeft:t.scrollLeft,scrollTop:t.scrollTop}:{scrollLeft:t.pageXOffset,scrollTop:t.pageYOffset}}function j(t){if(H(t)==="html")return t;const e=t.assignedSlot||t.parentNode||Ct(t)&&t.host||k(t);return Ct(e)?e.host:e}function Bt(t){const e=j(t);return ct(e)?t.ownerDocument?t.ownerDocument.body:t.body:F(e)&&Z(e)?e:Bt(e)}function G(t,e,n){var o;e===void 0&&(e=[]),n===void 0&&(n=!0);const i=Bt(t),s=i===((o=t.ownerDocument)==null?void 0:o.body),r=L(i);return s?e.concat(r,r.visualViewport||[],Z(i)?i:[],r.frameElement&&n?G(r.frameElement):[]):e.concat(i,G(i,[],n))}function Ht(t){const e=D(t);let n=parseFloat(e.width)||0,o=parseFloat(e.height)||0;const i=F(t),s=i?t.offsetWidth:n,r=i?t.offsetHeight:o,a=ot(n)!==s||ot(o)!==r;return a&&(n=s,o=r),{width:n,height:o,$:a}}function pt(t){return $(t)?t:t.contextElement}function I(t){const e=pt(t);if(!F(e))return P(1);const n=e.getBoundingClientRect(),{width:o,height:i,$:s}=Ht(e);let r=(s?ot(n.width):n.width)/o,a=(s?ot(n.height):n.height)/i;return(!r||!Number.isFinite(r))&&(r=1),(!a||!Number.isFinite(a))&&(a=1),{x:r,y:a}}const ae=P(0);function $t(t){const e=L(t);return!mt()||!e.visualViewport?ae:{x:e.visualViewport.offsetLeft,y:e.visualViewport.offsetTop}}function fe(t,e,n){return e===void 0&&(e=!1),!n||e&&n!==L(t)?!1:e}function V(t,e,n,o){e===void 0&&(e=!1),n===void 0&&(n=!1);const i=t.getBoundingClientRect(),s=pt(t);let r=P(1);e&&(o?$(o)&&(r=I(o)):r=I(t));const a=fe(s,n,o)?$t(s):P(0);let c=(i.left+a.x)/r.x,l=(i.top+a.y)/r.y,u=i.width/r.x,d=i.height/r.y;if(s){const m=L(s),f=o&&$(o)?L(o):o;let p=m,h=p.frameElement;for(;h&&o&&f!==p;){const w=I(h),g=h.getBoundingClientRect(),y=D(h),x=g.left+(h.clientLeft+parseFloat(y.paddingLeft))*w.x,O=g.top+(h.clientTop+parseFloat(y.paddingTop))*w.y;c*=w.x,l*=w.y,u*=w.x,d*=w.y,c+=x,l+=O,p=L(h),h=p.frameElement}}return rt({width:u,height:d,x:c,y:l})}const ue=[":popover-open",":modal"];function kt(t){return ue.some(e=>{try{return t.matches(e)}catch(n){return!1}})}function de(t){let{elements:e,rect:n,offsetParent:o,strategy:i}=t;const s=i==="fixed",r=k(o),a=e?kt(e.floating):!1;if(o===r||a&&s)return n;let c={scrollLeft:0,scrollTop:0},l=P(1);const u=P(0),d=F(o);if((d||!d&&!s)&&((H(o)!=="body"||Z(r))&&(c=at(o)),F(o))){const m=V(o);l=I(o),u.x=m.x+o.clientLeft,u.y=m.y+o.clientTop}return{width:n.width*l.x,height:n.height*l.y,x:n.x*l.x-c.scrollLeft*l.x+u.x,y:n.y*l.y-c.scrollTop*l.y+u.y}}function me(t){return Array.from(t.getClientRects())}function Pt(t){return V(k(t)).left+at(t).scrollLeft}function pe(t){const e=k(t),n=at(t),o=t.ownerDocument.body,i=T(e.scrollWidth,e.clientWidth,o.scrollWidth,o.clientWidth),s=T(e.scrollHeight,e.clientHeight,o.scrollHeight,o.clientHeight);let r=-n.scrollLeft+Pt(t);const a=-n.scrollTop;return D(o).direction==="rtl"&&(r+=T(e.clientWidth,o.clientWidth)-i),{width:i,height:s,x:r,y:a}}function he(t,e){const n=L(t),o=k(t),i=n.visualViewport;let s=o.clientWidth,r=o.clientHeight,a=0,c=0;if(i){s=i.width,r=i.height;const l=mt();(!l||l&&e==="fixed")&&(a=i.offsetLeft,c=i.offsetTop)}return{width:s,height:r,x:a,y:c}}function ge(t,e){const n=V(t,!0,e==="fixed"),o=n.top+t.clientTop,i=n.left+t.clientLeft,s=F(t)?I(t):P(1),r=t.clientWidth*s.x,a=t.clientHeight*s.y,c=i*s.x,l=o*s.y;return{width:r,height:a,x:c,y:l}}function At(t,e,n){let o;if(e==="viewport")o=he(t,n);else if(e==="document")o=pe(k(t));else if($(e))o=ge(e,n);else{const i=$t(t);o=E(C({},e),{x:e.x-i.x,y:e.y-i.y})}return rt(o)}function Wt(t,e){const n=j(t);return n===e||!$(n)||ct(n)?!1:D(n).position==="fixed"||Wt(n,e)}function ve(t,e){const n=e.get(t);if(n)return n;let o=G(t,[],!1).filter(a=>$(a)&&H(a)!=="body"),i=null;const s=D(t).position==="fixed";let r=s?j(t):t;for(;$(r)&&!ct(r);){const a=D(r),c=dt(r);!c&&a.position==="fixed"&&(i=null),(s?!c&&!i:!c&&a.position==="static"&&!!i&&["absolute","fixed"].includes(i.position)||Z(r)&&!c&&Wt(t,r))?o=o.filter(u=>u!==r):i=a,r=j(r)}return e.set(t,o),o}function we(t){let{element:e,boundary:n,rootBoundary:o,strategy:i}=t;const r=[...n==="clippingAncestors"?ve(e,this._c):[].concat(n),o],a=r[0],c=r.reduce((l,u)=>{const d=At(e,u,i);return l.top=T(d.top,l.top),l.right=K(d.right,l.right),l.bottom=K(d.bottom,l.bottom),l.left=T(d.left,l.left),l},At(e,a,i));return{width:c.right-c.left,height:c.bottom-c.top,x:c.left,y:c.top}}function ye(t){const{width:e,height:n}=Ht(t);return{width:e,height:n}}function xe(t,e,n){const o=F(e),i=k(e),s=n==="fixed",r=V(t,!0,s,e);let a={scrollLeft:0,scrollTop:0};const c=P(0);if(o||!o&&!s)if((H(e)!=="body"||Z(i))&&(a=at(e)),o){const d=V(e,!0,s,e);c.x=d.x+e.clientLeft,c.y=d.y+e.clientTop}else i&&(c.x=Pt(i));const l=r.left+a.scrollLeft-c.x,u=r.top+a.scrollTop-c.y;return{x:l,y:u,width:r.width,height:r.height}}function Et(t,e){return!F(t)||D(t).position==="fixed"?null:e?e(t):t.offsetParent}function Nt(t,e){const n=L(t);if(!F(t)||kt(t))return n;let o=Et(t,e);for(;o&&le(o)&&D(o).position==="static";)o=Et(o,e);return o&&(H(o)==="html"||H(o)==="body"&&D(o).position==="static"&&!dt(o))?n:o||ce(t)||n}const be=function(t){return B(this,null,function*(){const e=this.getOffsetParent||Nt,n=this.getDimensions;return{reference:xe(t.reference,yield e(t.floating),t.strategy),floating:C({x:0,y:0},yield n(t.floating))}})};function Re(t){return D(t).direction==="rtl"}const Oe={convertOffsetParentRelativeRectToViewportRelativeRect:de,getDocumentElement:k,getClippingRect:we,getOffsetParent:Nt,getElementRects:be,getClientRects:me,getDimensions:ye,getScale:I,isElement:$,isRTL:Re};function Ce(t,e){let n=null,o;const i=k(t);function s(){var a;clearTimeout(o),(a=n)==null||a.disconnect(),n=null}function r(a,c){a===void 0&&(a=!1),c===void 0&&(c=1),s();const{left:l,top:u,width:d,height:m}=t.getBoundingClientRect();if(a||e(),!d||!m)return;const f=nt(u),p=nt(i.clientWidth-(l+d)),h=nt(i.clientHeight-(u+m)),w=nt(l),y={rootMargin:-f+"px "+-p+"px "+-h+"px "+-w+"px",threshold:T(0,K(1,c))||1};let x=!0;function O(R){const M=R[0].intersectionRatio;if(M!==c){if(!x)return r();M?r(!1,M):o=setTimeout(()=>{r(!1,1e-7)},100)}x=!1}try{n=new IntersectionObserver(O,E(C({},y),{root:i.ownerDocument}))}catch(R){n=new IntersectionObserver(O,y)}n.observe(t)}return r(!0),s}function Ae(t,e,n,o){o===void 0&&(o={});const{ancestorScroll:i=!0,ancestorResize:s=!0,elementResize:r=typeof ResizeObserver=="function",layoutShift:a=typeof IntersectionObserver=="function",animationFrame:c=!1}=o,l=pt(t),u=i||s?[...l?G(l):[],...G(e)]:[];u.forEach(g=>{i&&g.addEventListener("scroll",n,{passive:!0}),s&&g.addEventListener("resize",n)});const d=l&&a?Ce(l,n):null;let m=-1,f=null;r&&(f=new ResizeObserver(g=>{let[y]=g;y&&y.target===l&&f&&(f.unobserve(e),cancelAnimationFrame(m),m=requestAnimationFrame(()=>{var x;(x=f)==null||x.observe(e)})),n()}),l&&!c&&f.observe(l),f.observe(e));let p,h=c?V(t):null;c&&w();function w(){const g=V(t);h&&(g.x!==h.x||g.y!==h.y||g.width!==h.width||g.height!==h.height)&&n(),h=g,p=requestAnimationFrame(w)}return n(),()=>{var g;u.forEach(y=>{i&&y.removeEventListener("scroll",n),s&&y.removeEventListener("resize",n)}),d==null||d(),(g=f)==null||g.disconnect(),f=null,c&&cancelAnimationFrame(p)}}const Ee=ne,Le=se,Lt=oe,Te=(t,e,n)=>{const o=new Map,i=C({platform:Oe},n),s=E(C({},i.platform),{_c:o});return ee(t,e,E(C({},i),{platform:s}))};function De(t){return t!=null&&typeof t=="object"&&"$el"in t}function Tt(t){if(De(t)){const e=t.$el;return ut(e)&&H(e)==="#comment"?null:e}return t}function Vt(t){return typeof window=="undefined"?1:(t.ownerDocument.defaultView||window).devicePixelRatio||1}function Dt(t,e){const n=Vt(t);return Math.round(e*n)/n}function Se(t,e,n){n===void 0&&(n={});const o=n.whileElementsMounted,i=v.computed(()=>{var b;return(b=v.unref(n.open))!=null?b:!0}),s=v.computed(()=>v.unref(n.middleware)),r=v.computed(()=>{var b;return(b=v.unref(n.placement))!=null?b:"bottom"}),a=v.computed(()=>{var b;return(b=v.unref(n.strategy))!=null?b:"absolute"}),c=v.computed(()=>{var b;return(b=v.unref(n.transform))!=null?b:!0}),l=v.computed(()=>Tt(t.value)),u=v.computed(()=>Tt(e.value)),d=v.ref(0),m=v.ref(0),f=v.ref(a.value),p=v.ref(r.value),h=v.shallowRef({}),w=v.ref(!1),g=v.computed(()=>{const b={position:f.value,left:"0",top:"0"};if(!u.value)return b;const A=Dt(u.value,d.value),S=Dt(u.value,m.value);return c.value?C(E(C({},b),{transform:"translate("+A+"px, "+S+"px)"}),Vt(u.value)>=1.5&&{willChange:"transform"}):{position:f.value,left:A+"px",top:S+"px"}});let y;function x(){l.value==null||u.value==null||Te(l.value,u.value,{middleware:s.value,placement:r.value,strategy:a.value}).then(b=>{d.value=b.x,m.value=b.y,f.value=b.strategy,p.value=b.placement,h.value=b.middlewareData,w.value=!0})}function O(){typeof y=="function"&&(y(),y=void 0)}function R(){if(O(),o===void 0){x();return}if(l.value!=null&&u.value!=null){y=o(l.value,u.value,x);return}}function M(){i.value||(w.value=!1)}return v.watch([s,r,a],x,{flush:"sync"}),v.watch([l,u],R,{flush:"sync"}),v.watch(i,M,{flush:"sync"}),v.getCurrentScope()&&v.onScopeDispose(O),{x:v.shallowReadonly(d),y:v.shallowReadonly(m),strategy:v.shallowReadonly(f),placement:v.shallowReadonly(p),middlewareData:v.shallowReadonly(h),isPositioned:v.shallowReadonly(w),floatingStyles:g,update:x}}function Me(t){return t&&"$el"in t?t.$el:t}const St=16,Fe=128;function Be(t,e,n){var d;const o=()=>{var m;return(m=e.value)==null?void 0:m.isExpanded()},i=[re(n==null?void 0:n.offset),Le({padding:St,apply({rects:m,elements:f,availableHeight:p,availableWidth:h}){Object.assign(f.floating.style,{width:"".concat(n!=null&&n.useAvailableWidth?h:m.reference.width,"px"),maxHeight:"".concat(Math.max(Fe,p),"px")})}}),Ee({padding:St,fallbackStrategy:"initialPlacement"}),Lt({strategy:"escaped"}),Lt()],{floatingStyles:s,placement:r,middlewareData:a,update:c}=Se(t,e,{middleware:i,placement:(d=n==null?void 0:n.placement)!=null?d:"bottom"}),l=v.computed(()=>{var f,p;return!o()||!!((f=a.value.hide)!=null&&f.escaped)||((p=a.value.hide)==null?void 0:p.referenceHidden)?"hidden":"visible"});v.watch([s,l,r],([m,f,p])=>{var h,w,g,y,x;Object.assign((w=(h=e.value)==null?void 0:h.$el.style)!=null?w:{},{visibility:f,position:m.position,top:"".concat(m.top,"px"),right:"unset",left:"".concat(m.left,"px"),transform:(g=m.transform)!=null?g:"none",borderTopLeftRadius:p==="bottom"&&f==="visible"?"0":"",borderTopRightRadius:p==="bottom"&&f==="visible"?"0":"",borderBottomLeftRadius:p==="top"&&f==="visible"?"0":"",borderBottomRightRadius:p==="top"&&f==="visible"?"0":""}),Object.assign((x=(y=Me(t.value))==null?void 0:y.style)!=null?x:{},{borderTopLeftRadius:p==="top"&&f==="visible"?"0":"",borderTopRightRadius:p==="top"&&f==="visible"?"0":"",borderBottomLeftRadius:p==="bottom"&&f==="visible"?"0":"",borderBottomRightRadius:p==="bottom"&&f==="visible"?"0":""})});let u=null;v.watch(o,m=>{var f;m?u=Ae(t.value&&"$el"in t.value?t.value.$el:t,(f=e.value)==null?void 0:f.$el,c):u&&(u(),u=null)})}module.exports=Be;
