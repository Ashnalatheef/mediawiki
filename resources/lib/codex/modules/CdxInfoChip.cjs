"use strict";const e=require("vue"),i=require("./constants.js"),n=require("./Icon.js"),r=require("./_plugin-vue_export-helper.js"),a={error:n.i4,warning:n.M3,success:n.N7},u=e.defineComponent({name:"CdxInfoChip",components:{CdxIcon:n.CdxIcon},props:{status:{type:String,default:"notice",validator:i.statusTypeValidator},icon:{type:[String,Object],default:null}},setup(o){const t=e.computed(()=>({["cdx-info-chip__icon--".concat(o.status)]:!0})),c=e.computed(()=>o.status==="notice"?o.icon:a[o.status]);return{iconClass:t,computedIcon:c}}}),d={class:"cdx-info-chip"},l={class:"cdx-info-chip--text"};function p(o,t,c,m,f,C){const s=e.resolveComponent("cdx-icon");return e.openBlock(),e.createElementBlock("div",d,[o.computedIcon?(e.openBlock(),e.createBlock(s,{key:0,class:e.normalizeClass(["cdx-info-chip__icon",o.iconClass]),icon:o.computedIcon},null,8,["class","icon"])):e.createCommentVNode("v-if",!0),e.createElementVNode("span",l,[e.renderSlot(o.$slots,"default")])])}const _=r._export_sfc(u,[["render",p]]);module.exports=_;
