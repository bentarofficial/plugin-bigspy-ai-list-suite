(function(){
 const cfg=window.BIGSPY_POPULAR;if(!cfg||!cfg.listingId)return;
 let sent=false;
 document.addEventListener('click',function(event){
  const link=event.target.closest('.bigspy-single-hero__cta a');
  if(!link||sent)return;sent=true;
  const body=new URLSearchParams({action:'bigspy_popular_click',nonce:cfg.nonce,listing_id:String(cfg.listingId)});
  fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString(),keepalive:true}).catch(function(){});
 },{capture:true});
})();