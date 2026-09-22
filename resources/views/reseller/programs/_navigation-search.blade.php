@php
 $navigationChoices=[];
 foreach(['Dashboard'=>'reseller.dashboard','Referral Programs'=>'reseller.programs.index','My Referrals'=>'reseller.deals','Rewards'=>'reseller.commission','Messages'=>'reseller.messages','Activity'=>'reseller.activity','Profile'=>'reseller.profile'] as $label=>$destination) {
  $navigationChoices[]=['label'=>$label,'url'=>route($destination,['tenantId'=>$tenant->id,'program_id'=>$program->id])];
 }
 foreach($programs as $choice) $navigationChoices[]=['label'=>$choice->name,'url'=>route('reseller.dashboard',['tenantId'=>$tenant->id,'program_id'=>$choice->id])];
@endphp
<div class="rb-navigation-search" x-data="{ query: '', open: false, choices: @js($navigationChoices), get matches() { return this.choices.filter(item => item.label.toLowerCase().includes(this.query.trim().toLowerCase())); } }" @click.outside="open=false" @keydown.escape="open=false">
 <label class="sr-only" for="dashboard-navigation-search">Search your programs or pages</label>
 <input id="dashboard-navigation-search" type="search" placeholder="Search your programs or pages…" autocomplete="off" x-model="query" @focus="open=true" @input="open=true" :aria-expanded="open" aria-controls="dashboard-navigation-results">
 <div class="rb-search-results" id="dashboard-navigation-results" x-show="open" x-cloak>
  <template x-for="(item,index) in matches" :key="index"><a :href="item.url" x-text="item.label"></a></template>
  <p x-show="matches.length===0" role="status">No matching programs or pages.</p>
 </div>
</div>
