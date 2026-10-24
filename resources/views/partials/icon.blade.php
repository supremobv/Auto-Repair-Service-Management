@php
$paths=[
'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'calendar'=>'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2z M7 14h2m3 0h2m3 0h1M7 17h2m3 0h2',
'tool'=>'M14 6a5 5 0 0 0-6 6L3 17a3 3 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-4 2-2-2z',
'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18 0v-2a4 4 0 0 0-3-4M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8m8 0a4 4 0 0 1 0 8',
'car'=>'M5 17H3v-6l3-6h12l3 6v6h-2M5 17h14M3 11h18M5 17v3m14-3v3M6 14h2m8 0h2',
'receipt'=>'M5 3l3 2 4-2 4 2 3-2v19l-3-2-4 2-4-2-3 2zM8 9h8M8 13h8m-8 4h5',
'box'=>'M12 3l9 5v10l-9 5-9-5V8zM3 8l9 5 9-5M12 13v10M7 5l9 5',
'list'=>'M8 5h13M8 12h13M8 19h13M3 5h1m-1 7h1m-1 7h1',
'truck'=>'M1 4h14v13H1zM15 9h4l3 4v4h-7M7 19a2 2 0 1 0-4 0 2 2 0 0 0 4 0m13 0a2 2 0 1 0-4 0 2 2 0 0 0 4 0',
'chart'=>'M3 3v18h18M8 16v-4m5 4V7m5 9V4',
'shield'=>'M12 2l9 4v6c0 5-9 10-9 10S3 17 3 12V6zM8 12l3 3 5-6',
'clock'=>'M12 8v5l3 2M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0',
'settings'=>'M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1zM15 12a3 3 0 1 0-6 0 3 3 0 0 0 6 0',
'help'=>'M9 9a3 3 0 1 1 4 3v2m-1 3h.01M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0',
'arrow'=>'M5 12h14m-5-5 5 5-5 5',
];
@endphp
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name]??$paths['grid'] }}"/></svg>
