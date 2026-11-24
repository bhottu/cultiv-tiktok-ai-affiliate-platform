@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('header', 'Log Aktivitas Sistem')
@section('subheader', 'Audit trail terenkripsi & bebas kredensial (zona waktu Asia/Jakarta)')

@section('content')
<div class="panel overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-slate-50 uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Waktu (WIB)</th>
                    <th class="px-4 py-3 text-left">Modul</th>
                    <th class="px-4 py-3 text-left">Aksi</th>
                    <th class="px-4 py-3 text-left">Level</th>
                    <th class="px-4 py-3 text-left">Deskripsi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-2.5 whitespace-nowrap text-slate-500">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                        <td class="px-4 py-2.5 font-mono text-slate-700">{{ $log->module }}</td>
                        <td class="px-4 py-2.5 font-mono text-slate-600">{{ $log->action }}</td>
                        <td class="px-4 py-2.5">
                            <span class="{{ $log->level === 'error' ? 'badge-err' : ($log->level === 'warning' ? 'badge-warn' : 'badge-muted') }}">{{ $log->level }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-700">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada log aktivitas tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
