<div class="rounded-xl bg-purple-50 p-4 text-sm space-y-2">
    <p><strong>{{ $term['name'] }} · {{ $term['label'] }} reward</strong> <span class="text-xs text-gray-500">{{ $term['currency'] }} · Version {{ $term['version'] }}</span></p>
    @if($term['supported'])
    <p>{{ $term['model'] === 'percentage' ? 'Calculated on collected revenue excluding tax.' : 'Fixed reward per eligible payment.' }} {{ $term['scope'] }}</p>
    <p>Rewards are held for {{ $term['hold'] }} days and require manual approval. Refunds reverse the associated reward. Self-referrals and existing customers are excluded.</p>
    @else
    <p>Review this offer’s published rules for its qualifying event and calculation. No estimate is shown for this reward model.</p>
    @endif
</div>
