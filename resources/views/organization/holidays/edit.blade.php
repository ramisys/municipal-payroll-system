@extends('layouts.app')

@section('title', 'Edit holiday')
@section('heading', 'Edit holiday')

@section('content')
    <x-page-header title="Edit holiday" subtitle="{{ $holiday->holiday_name }}"
                   :back="route('organization.holidays.index')" back-label="Holiday calendar" />

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('organization.holidays.update', $holiday) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @include('organization.holidays._fields')

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('organization.holidays.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
