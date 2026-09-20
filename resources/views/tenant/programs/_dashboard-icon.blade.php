@php
$paths=[
 'click'=>'M4 3l6 17 3-7 7-3L4 3zm9 10 7 7',
 'revenue'=>'M4 20V12M10 20V8M16 20V4M22 20V10',
 'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 'renew'=>'M20 7v5h-5M4 17v-5h5M6.1 6.1A8 8 0 0 1 20 12M4 12a8 8 0 0 0 13.9 5.9',
 'trend'=>'M3 17l6-6 4 4 8-10M15 5h6v6',
 'gift'=>'M3 8h18v4H3zM5 12v9h14v-9M12 8v13M12 8H7.5A2.5 2.5 0 1 1 10 5.5L12 8zm0 0h4.5A2.5 2.5 0 1 0 14 5.5L12 8z',
 'wallet'=>'M3 5h16v4H3zM3 9v12h18V9H3zm13 4h5v4h-5z',
];
@endphp
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$icon] ?? $paths['gift'] }}"/></svg>
