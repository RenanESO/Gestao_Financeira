@if(session('status'))
    <div role="status" class="mb-5 rounded-xl border border-primary/30 bg-primary/10 p-4 text-sm">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div role="alert" class="mb-5 rounded-xl border border-error/30 bg-error/10 p-4 text-sm">
        <p class="mb-2 font-semibold">Confira os campos informados:</p>
        <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
