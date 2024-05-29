"use strict";const t=require("vue"),y=require("./Icon.js"),f=require("./CdxThumbnail.cjs"),b=require("./CdxSearchResultTitle.cjs"),k=require("./_plugin-vue_export-helper.js"),v=t.defineComponent({name:"CdxMenuItem",components:{CdxIcon:y.CdxIcon,CdxThumbnail:f,CdxSearchResultTitle:b},props:{id:{type:String,required:!0},value:{type:[String,Number],required:!0},disabled:{type:Boolean,default:!1},selected:{type:Boolean,default:!1},active:{type:Boolean,default:!1},highlighted:{type:Boolean,default:!1},label:{type:String,default:""},match:{type:String,default:""},supportingText:{type:String,default:""},url:{type:String,default:""},icon:{type:[String,Object],default:""},showThumbnail:{type:Boolean,default:!1},thumbnail:{type:[Object,null],default:null},description:{type:[String,null],default:""},searchQuery:{type:String,default:""},boldLabel:{type:Boolean,default:!1},hideDescriptionOverflow:{type:Boolean,default:!1},language:{type:Object,default:()=>({})},action:{type:String,default:"default"}},emits:["change"],setup:(e,{emit:n})=>{const s=()=>{e.highlighted||n("change","highlighted",!0)},d=()=>{n("change","highlighted",!1)},r=i=>{i.button===0&&n("change","active",!0)},c=()=>{n("change","selected",!0)},o=t.computed(()=>e.searchQuery.length>0),u=t.computed(()=>({"cdx-menu-item--selected":e.selected,"cdx-menu-item--active":e.active&&e.highlighted,"cdx-menu-item--highlighted":e.highlighted,"cdx-menu-item--destructive":e.action&&e.action==="destructive","cdx-menu-item--enabled":!e.disabled,"cdx-menu-item--disabled":e.disabled,"cdx-menu-item--highlight-query":o.value,"cdx-menu-item--bold-label":e.boldLabel,"cdx-menu-item--has-description":!!e.description,"cdx-menu-item--hide-description-overflow":e.hideDescriptionOverflow})),a=t.computed(()=>e.url?"a":"span"),l=t.computed(()=>e.label||String(e.value));return{onMouseMove:s,onMouseLeave:d,onMouseDown:r,onClick:c,highlightQuery:o,rootClasses:u,contentTag:a,title:l}}}),B=["id","aria-disabled","aria-selected"],C={class:"cdx-menu-item__text"},S=["lang"],M=["lang"],N=["lang"],V=["lang"];function q(e,n,s,d,r,c){const o=t.resolveComponent("cdx-thumbnail"),u=t.resolveComponent("cdx-icon"),a=t.resolveComponent("cdx-search-result-title");return t.openBlock(),t.createElementBlock("li",{id:e.id,role:"option",class:t.normalizeClass(["cdx-menu-item",e.rootClasses]),"aria-disabled":e.disabled,"aria-selected":e.selected,onMousemove:n[0]||(n[0]=(...l)=>e.onMouseMove&&e.onMouseMove(...l)),onMouseleave:n[1]||(n[1]=(...l)=>e.onMouseLeave&&e.onMouseLeave(...l)),onMousedown:n[2]||(n[2]=t.withModifiers((...l)=>e.onMouseDown&&e.onMouseDown(...l),["prevent"])),onClick:n[3]||(n[3]=(...l)=>e.onClick&&e.onClick(...l))},[t.renderSlot(e.$slots,"default",{},()=>[(t.openBlock(),t.createBlock(t.resolveDynamicComponent(e.contentTag),{href:e.url?e.url:void 0,class:"cdx-menu-item__content"},{default:t.withCtx(()=>{var l,i,m,g,h,p;return[e.showThumbnail?(t.openBlock(),t.createBlock(o,{key:0,thumbnail:e.thumbnail,class:"cdx-menu-item__thumbnail"},null,8,["thumbnail"])):e.icon?(t.openBlock(),t.createBlock(u,{key:1,icon:e.icon,class:"cdx-menu-item__icon"},null,8,["icon"])):t.createCommentVNode("v-if",!0),t.createElementVNode("span",C,[e.highlightQuery?(t.openBlock(),t.createBlock(a,{key:0,title:e.title,"search-query":e.searchQuery,lang:(l=e.language)==null?void 0:l.label},null,8,["title","search-query","lang"])):(t.openBlock(),t.createElementBlock("span",{key:1,class:"cdx-menu-item__text__label",lang:(i=e.language)==null?void 0:i.label},[t.createElementVNode("bdi",null,t.toDisplayString(e.title),1)],8,S)),e.match?(t.openBlock(),t.createElementBlock(t.Fragment,{key:2},[t.createTextVNode(t.toDisplayString(" ")+" "),e.highlightQuery?(t.openBlock(),t.createBlock(a,{key:0,title:e.match,"search-query":e.searchQuery,lang:(m=e.language)==null?void 0:m.match},null,8,["title","search-query","lang"])):(t.openBlock(),t.createElementBlock("span",{key:1,class:"cdx-menu-item__text__match",lang:(g=e.language)==null?void 0:g.match},[t.createElementVNode("bdi",null,t.toDisplayString(e.match),1)],8,M))],64)):t.createCommentVNode("v-if",!0),e.supportingText?(t.openBlock(),t.createElementBlock(t.Fragment,{key:3},[t.createTextVNode(t.toDisplayString(" ")+" "),t.createElementVNode("span",{class:"cdx-menu-item__text__supporting-text",lang:(h=e.language)==null?void 0:h.supportingText},[t.createElementVNode("bdi",null,t.toDisplayString(e.supportingText),1)],8,N)],64)):t.createCommentVNode("v-if",!0),e.description?(t.openBlock(),t.createElementBlock("span",{key:4,class:"cdx-menu-item__text__description",lang:(p=e.language)==null?void 0:p.description},[t.createElementVNode("bdi",null,t.toDisplayString(e.description),1)],8,V)):t.createCommentVNode("v-if",!0)])]}),_:1},8,["href"]))])],42,B)}const D=k._export_sfc(v,[["render",q]]);module.exports=D;
