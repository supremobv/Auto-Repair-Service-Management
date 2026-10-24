@extends('layouts.app')
@section('title','Stock history')
@section('content')
<div class="page-heading"><div><h1>Stock history</h1><p>Trace every receipt, adjustment and part used on a work order.</p></div><a class="btn" href="/manage/inventory">← Parts & inventory</a></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>When</th><th>Part</th><th>Change</th><th>Reason / work order</th><th>Team member</th></tr></thead><tbody>@forelse($movements as $m)<tr><td>{{ $m->created_at }}</td><td>{{ $m->name }}<small>{{ $m->sku }}</small></td><td><span class="badge {{ $m->quantity>0?'status-completed':'status-estimate' }}">{{ $m->quantity>0?'+':'' }}{{ $m->quantity }}</span></td><td>{{ $m->reason }}</td><td>{{ $m->user_name??'System' }}</td></tr>@empty<tr><td colspan="5" class="empty">No stock movements yet. Receive stock from a part’s edit page.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}</section>
@endsection
