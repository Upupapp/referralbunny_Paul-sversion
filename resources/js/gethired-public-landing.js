export default (options) => ({
 selectedPackage:options.defaultPackage??'',busy:false,errors:{},state:null,email:'',message:'',
 get calculation(){return (options.packages??[]).find(p=>p.id===this.selectedPackage)?.estimate??null},
 money(minor){return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',minimumFractionDigits:0,maximumFractionDigits:0}).format(minor/100)},
 openModal(){this.$refs.result.showModal()},
 closeModal(){this.$refs.result.close()},
 async submit(event){
  if(this.busy||options.preview||!options.open)return;
  const form=event.target,body=new FormData(form);this.busy=true;this.errors={};this.state=null;
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),25000);
  try {
   const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json'},signal:controller.signal,credentials:'same-origin'});
   const data=await response.json().catch(()=>({}));
   if(!response.ok){
    if(data.errors){this.errors=data.errors;this.$nextTick(()=>form.querySelector('[aria-invalid="true"]')?.focus());return;}
    this.state='error';this.message=response.status===419?'Your session expired. Reload this page before trying again.':(response.status>=500?'We couldn’t send your invite right now. Please try again in a moment.':(data.message||'We couldn’t send your invite right now. Please try again in a moment.'));this.openModal();return;
   }
   if(!['sent','resent','existing'].includes(data.state))throw new Error('Unexpected response');
   this.state=data.state;this.email=data.email;this.openModal();
  } catch {this.state='error';this.message='We couldn’t confirm delivery. Check your inbox before trying again.';this.openModal();}
  finally {clearTimeout(timer);this.busy=false;}
 }
});
