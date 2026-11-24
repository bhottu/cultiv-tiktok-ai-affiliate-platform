@extends('layouts.app')

@section('title', 'Konfigurasi AI Agent')
@section('header', 'Registri AI Agent Plant Coaction')
@section('subheader', 'Struktur modular agent siap diaktifkan pada fase riset, strategi, produksi, dan publikasi')

@section('content')
<div class="panel overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Agent</th>
                    <th class="px-4 py-3 text-left">Modul</th>
                    <th class="px-4 py-3 text-left">Deskripsi Peran</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($agents as $agent)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $agent->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $agent->module }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $agent->role_description }}</td>
                        <td class="px-4 py-3"><span class="badge-muted">{{ $agent->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-xs text-slate-500">Belum ada konfigurasi agent.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
