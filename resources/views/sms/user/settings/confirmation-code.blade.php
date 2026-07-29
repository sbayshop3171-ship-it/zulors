@extends('sms.layouts.main')

@section('sms_content')
	{{ __('sms.code.title') }}
	
	{{ $code }}

	@if(isset($ignoreSms))
		{{ $ignoreSms }}
	@endif
@endsection