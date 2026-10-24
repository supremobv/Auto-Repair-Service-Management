@php
    use App\Support\Access;
    $nav = [
        ['Workspace', [['/', 'Overview','dashboard','grid'],['/manage/appointments','Appointments','appointments.read','calendar'],['/orders','Work orders','orders.read','tool'],['/manage/customers','Customers','customers.read','users'],['/manage/vehicles','Vehicles','vehicles.read','car']]],
        ['Business', [['/invoices','Invoices & payments','billing.read','receipt'],['/manage/inventory','Parts & inventory','inventory.read','box'],['/manage/services','Service catalog','services.read','list'],['/manage/suppliers','Suppliers','suppliers.read','truck'],['/reports','Reports','reports','chart']]],
        ['Administration', [['/staff','Team & access','users','shield'],['/audit','Activity log','audit','clock'],['/settings','Business settings','settings','settings']]],
    ];
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Overview') · {{ $shop->name ?? 'Torque Workshop' }}</title><link rel="icon" href="/logo.svg" type="image/svg+xml"><link rel="stylesheet" href="/app.css"><script src="/app.js" defer></script></head>
<body>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="/"><img src="{{ !empty($shop->logo) ? asset('storage/'.$shop->logo) : '/logo.svg' }}" alt="Business logo"><span>{{ $shop->name ?? 'Torque Workshop' }}<small>SERVICE MANAGEMENT</small></span></a>
    <div class="location"><span class="live-dot"></span> Workshop workspace <span class="muted">⌄</span></div>
    <nav aria-label="Main navigation">@foreach($nav as [$group,$links])<div class="nav-label">{{ $group }}</div>@foreach($links as [$url,$label,$ability,$icon])@if(Access::allows(auth()->user(),$ability))<a href="{{ $url }}" class="nav-link {{ ($url==='/' ? request()->is('/') : str_starts_with('/'.request()->path(),$url)) ? 'active':'' }}">@include('partials.icon',['name'=>$icon])<span>{{ $label }}</span>@if($url==='/orders')<span class="nav-dot"></span>@endif</a>@endif @endforeach @endforeach</nav>
    <div class="sidebar-bottom"><a href="/help" class="nav-link">@include('partials.icon',['name'=>'help'])<span>Help & user manual</span></a><a href="/password" class="account"><span class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span>{{ auth()->user()->name }}<small>{{ ucfirst(auth()->user()->role) }}</small></span><span class="muted">↗</span></a><form method="post" action="/logout">@csrf<button class="logout" type="submit">Sign out</button></form></div>
</aside>
<div class="workspace"><header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-toggle" data-action="sidebar" aria-label="Toggle navigation">☰</button><span>Workspace</span><span class="slash">/</span><strong>@yield('title','Overview')</strong></div><div class="topbar-right"><span class="environment"><span class="live-dot"></span> Local workspace</span><span class="top-date">{{ now()->format('D, M j, Y') }}</span><a class="icon-button" href="/help" aria-label="Open help">?</a></div></header>
<main id="main-content">@if(session('success'))<div class="alert success" role="status">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer"><span>{{ $shop->name ?? 'Torque Workshop' }} · Built for a better workshop.</span><span>Workshop management · v1.0</span></footer></main></div>
</body></html>
