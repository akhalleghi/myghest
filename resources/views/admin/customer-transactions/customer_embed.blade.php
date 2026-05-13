@extends('layouts.admin.embed_iframe')

@section('title', $pageTitle)

@push('head')
    @include('admin.customer-transactions.partials.ctx-head-stack')
@endpush

@section('content')
    @include('admin.customer-transactions.partials.ctx-body')
@endsection

@push('scripts')
    @include('admin.customer-transactions.partials.ctx-scripts')
@endpush
