(function(e){function n(t){return this.filter(n.resizableFilterSelector).each(function(){new r(e(this),t)}),this}function r(t,r){r=this.config=e.extend(n.defaults,r),this.el=t,this.nodeName=t[0].nodeName.toLowerCase(),this.originalHeight=t.height(),this.previousScrollTop=null,this.value=t.val(),r.maxWidth==="original"&&(r.maxWidth=t.width()),r.minWidth==="original"&&(r.minWidth=t.width()),r.maxHeight==="original"&&(r.maxHeight=t.height()),r.minHeight==="original"&&(r.minHeight=t.height()),this.nodeName==="textarea"&&t.css({resize:"none",overflowY:"hidden"}),t.data("AutoResizer",this),this.createClone(),this.injectClone(),this.bind()}var t=n.defaults={onResize:function(){},animate:{duration:200,complete:function(){}},extraSpace:50,minHeight:"original",maxHeight:500,minWidth:"original",maxWidth:500};n.cloneCSSProperties=["lineHeight","textDecoration","letterSpacing","fontSize","fontFamily","fontStyle","fontWeight","textTransform","textAlign","direction","wordSpacing","fontSizeAdjust","padding"],n.cloneCSSValues={position:"absolute",top:-9999,left:-9999,opacity:0,overflow:"hidden"},n.resizableFilterSelector="textarea,input:not(input[type]),input[type=text],input[type=password]",n.AutoResizer=r,e.fn.autoResize=n,r.prototype={bind:function(){var t=e.proxy(function(){return this.check(),!0},this);this.unbind(),this.el.bind("keyup.autoResize",t).bind("change.autoResize",t),this.check(null,!0)},unbind:function(){this.el.unbind(".autoResize")},createClone:function(){var t=this.el,r;this.nodeName==="textarea"?r=t.clone().height("auto"):r=e("<span/>").width("auto").css({whiteSpace:"nowrap"}),this.clone=r,e.each(n.cloneCSSProperties,function(e,n){r[0].style[n]=t.css(n)}),r.removeAttr("name").removeAttr("id").attr("tabIndex",-1).css(n.cloneCSSValues)},check:function(e,t){var n=this.config,r=this.clone,i=this.el,s=i.val();if(this.nodeName==="input"){r.text(s);var o=r.width(),u=o+n.extraSpace>=n.minWidth?o+n.extraSpace:n.minWidth,a=i.width();u=Math.min(u,n.maxWidth);if(u<a&&u>=n.minWidth||u>=n.minWidth&&u<=n.maxWidth)n.onResize.call(i),i.scrollLeft(0),n.animate&&!t?i.stop(1,1).animate({width:u},n.animate):i.width(u);return}r.height(0).val(s).scrollTop(1e4);var f=r[0].scrollTop+n.extraSpace;if(this.previousScrollTop===f)return;this.previousScrollTop=f;if(f>=n.maxHeight){i.css("overflowY","");return}i.css("overflowY","hidden"),f<n.minHeight&&(f=n.minHeight),n.onResize.call(i),n.animate&&!t?i.stop(1,1).animate({height:f},n.animate):i.height(f)},destroy:function(){this.unbind(),this.el.removeData("AutoResizer"),this.clone.remove(),delete this.el,delete this.clone},injectClone:function(){(n.cloneContainer||(n.cloneContainer=e("<arclones/>").appendTo("body"))).append(this.clone)}}})(jQuery)