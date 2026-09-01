@extends('layouts.app')

@section('title', 'New holiday')
@section('heading', 'New holiday')

@section('content')
    <x-page-header title="New holiday"
                   :back="route('organization.holidays.index')" back-label="Holiday calendar" />

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('organization.holidays.store') }}" class="space-y-4">
                @csrf
                @include('organization.holidays._fields', ['holiday' => null])

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary"><x-icon name="save" />Save holiday</button>
                    <a href="{{ route('organization.holidays.index') }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
