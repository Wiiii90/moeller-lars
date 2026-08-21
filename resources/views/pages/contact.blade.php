@extends('layouts.app')

@section('title', 'Contact · Lars Möller')
@section('meta_description', 'Contact Lars Möller')
@section('canonical', app(\App\Domain\Content\CanonicalUrl::class)->forPath('/contact'))

@section('content')
    <x-contact :general-settings="$generalSettings" :contact-settings="$contactSettings" />
@endsection
