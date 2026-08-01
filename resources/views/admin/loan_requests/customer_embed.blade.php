@extends('layouts.admin.embed_iframe')

@section('title', $pageTitle)

@push('head')
    @include('admin.loan_requests.partials.lrq-head-stack')
@endpush

@section('content')
    @include('admin.loan_requests.partials.lrq-body')
@endsection

@push('scripts')
    @vite(['resources/js/admin-modal-windowing.js'])
    @include('admin.loan_requests.partials.lrq-scripts')
@endpush
