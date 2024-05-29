"use strict";const t=require("vue"),i=require("./useIconOnlyButton.js"),r=require("./_plugin-vue_export-helper.js"),c=t.defineComponent({name:"CdxToggleButton",props:{modelValue:{type:Boolean,default:!1},disabled:{type:Boolean,default:!1},quiet:{type:Boolean,default:!1}},emits:["update:modelValue"],setup(e,{emit:o,slots:s,attrs:u}){const d=i.useIconOnlyButton(s.default,u,"CdxButton"),n=t.ref(!1);return{rootClasses:t.computed(()=>({"cdx-toggle-button--quiet":e.quiet,"cdx-toggle-button--framed":!e.quiet,"cdx-toggle-button--toggled-on":e.modelValue,"cdx-toggle-button--toggled-off":!e.modelValue,"cdx-toggle-button--icon-only":d.value,"cdx-toggle-button--is-active":n.value})),onClick:()=>{o("update:modelValue",!e.modelValue)},setActive:a=>{n.value=a}}}}),g=["aria-pressed","disabled"];function f(e,o,s,u,d,n){return t.openBlock(),t.createElementBlock("button",{class:t.normalizeClass(["cdx-toggle-button",e.rootClasses]),"aria-pressed":e.modelValue,disabled:e.disabled,onClick:o[0]||(o[0]=(...l)=>e.onClick&&e.onClick(...l)),onKeydown:o[1]||(o[1]=t.withKeys(l=>e.setActive(!0),["space","enter"])),onKeyup:o[2]||(o[2]=t.withKeys(l=>e.setActive(!1),["space","enter"]))},[t.renderSlot(e.$slots,"default")],42,g)}const p=r._export_sfc(c,[["render",f]]);module.exports=p;
