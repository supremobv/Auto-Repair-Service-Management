@extends('layouts.app')
@section('title','Activity log')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ACCOUNTABILITY, BUILT IN</div><h1>Activity log</h1><p>Read-only history of sign-ins and workshop changes.</p></div></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>When</th><th>Team member</th><th>Action</th><th>Record</th><th>IP address</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->name??'System' }}</td><td>{{ $log->action }}</td><td>{{ $log->subject }}</td><td>{{ $log->ip??'—' }}</td></tr>@empty<tr><td colspan="5" class="empty">No activity recorded.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}</section>
@endsection
