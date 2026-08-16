@extends('layouts.app')

@section('title', 'Contact · Lars Möller')

@section('content')
    <x-contact :settings="$settings" />
@endsection
