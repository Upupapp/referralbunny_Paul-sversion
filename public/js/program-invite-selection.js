(()=>{
 const button=document.getElementById('bulk-resend-open');if(!button)return;
 const dialog=document.getElementById('bulk-resend-dialog'),form=document.getElementById('bulk-resend-form');let busy=false;
 button.onclick=()=>{
  const selected=[...document.querySelectorAll('.invite-select:checked')];
  const recipients=document.getElementById('bulk-resend-recipients'),inputs=document.getElementById('bulk-resend-members');recipients.replaceChildren();inputs.replaceChildren();
  if(!selected.length||selected.length>20){button.textContent='Select between 1 and 20 pending invitations first';return;}
  button.textContent='Review selected invitations for resend';
  selected.forEach(item=>{const li=document.createElement('li');li.textContent=item.dataset.email;recipients.appendChild(li);const input=document.createElement('input');input.type='hidden';input.name='members[]';input.value=item.value;inputs.appendChild(input);});
  form.querySelector('[name="confirmed"]').checked=false;dialog.showModal();
 };
 form.onsubmit=e=>{if(busy){e.preventDefault();return;}busy=true;form.querySelectorAll('button').forEach(b=>b.disabled=true);document.getElementById('bulk-resend-progress').textContent='Sending selected invitations. Keep this page open; results will appear when complete.';};
 dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});
})();
