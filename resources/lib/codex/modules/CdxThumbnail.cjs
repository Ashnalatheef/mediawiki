"use strict";const e=require("vue"),s=require("./Icon.js"),u=require("./constants.js"),i=require("./_plugin-vue_export-helper.js"),d=e.defineComponent({name:"CdxThumbnail",components:{CdxIcon:s.CdxIcon},props:{thumbnail:{type:[Object,null],default:null},placeholderIcon:{type:[String,Object],default:s.O4}},setup:n=>{const l=e.ref(!1),a=e.ref({}),r=o=>{const c=o.replace(/([\\"\n])/g,"\\$1"),t=new Image;t.onload=()=>{a.value={backgroundImage:'url("'.concat(c,'")')},l.value=!0},t.onerror=()=>{l.value=!1},t.src=c};return e.onMounted(()=>{var o;(o=n.thumbnail)!=null&&o.url&&r(n.thumbnail.url)}),{thumbnailStyle:a,thumbnailLoaded:l,NoInvertClass:u.NoInvertClass}}}),m={class:"cdx-thumbnail"},p={key:0,class:"cdx-thumbnail__placeholder"};function h(n,l,a,r,o,c){const t=e.resolveComponent("cdx-icon");return e.openBlock(),e.createElementBlock("span",m,[n.thumbnailLoaded?e.createCommentVNode("v-if",!0):(e.openBlock(),e.createElementBlock("span",p,[e.createVNode(t,{icon:n.placeholderIcon,class:"cdx-thumbnail__placeholder__icon--vue"},null,8,["icon"])])),e.createVNode(e.Transition,{name:"cdx-thumbnail__image"},{default:e.withCtx(()=>[n.thumbnailLoaded?(e.openBlock(),e.createElementBlock("span",{key:0,style:e.normalizeStyle(n.thumbnailStyle),class:e.normalizeClass([n.NoInvertClass,"cdx-thumbnail__image"])},null,6)):e.createCommentVNode("v-if",!0)]),_:1})])}const _=i._export_sfc(d,[["render",h]]);module.exports=_;
