(() => {
 if (!document.querySelector('[data-invite-delivery]')) return;
 setInterval(async () => {
  if(document.hidden || document.querySelector('dialog[open]') || document.activeElement?.closest('form'))return;
  try {
   const response=await fetch(location.href,{headers:{'Accept':'text/html'},credentials:'same-origin'});
   if(!response.ok || response.redirected)return;
   const page=new DOMParser().parseFromString(await response.text(),'text/html');
   document.querySelectorAll('[data-invite-delivery]').forEach(cell=>{
    const updated=page.querySelector(`[data-invite-delivery="${CSS.escape(cell.dataset.inviteDelivery)}"]`);
    if(updated && !cell.querySelector('details[open]'))cell.replaceWith(document.importNode(updated,true));
   });
  }catch(e){}
 },60000);
})();
