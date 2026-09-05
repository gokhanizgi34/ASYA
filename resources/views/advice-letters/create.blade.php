<x-layouts.app title="Mukaddes Abla · Yeni Mektup">
<section class="mx-auto max-w-3xl"><header><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">MUKADDES ABLA</p><h1 class="mt-3 text-4xl font-black">Yeni mektup</h1></header>
<form method="POST" action="{{ route('advice-letters.store') }}" class="mt-8 space-y-5 rounded-2xl border border-white/10 bg-white/[.04] p-6">
@csrf
<label class="block"><span class="mb-2 block font-bold">Ajans</span><select name="agency_id" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(old('agency_id') == $agency->id)>{{ $agency->name }}</option>@endforeach</select></label>
<label class="block"><span class="mb-2 block font-bold">Takma ad</span><input name="pseudonym" value="{{ old('pseudonym') }}" required maxlength="80" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
<label class="block"><span class="mb-2 block font-bold">Konu</span><input name="category" value="{{ old('category', 'personal') }}" required maxlength="40" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
<label class="block"><span class="mb-2 block font-bold">Soru / mektup</span><textarea name="question" rows="9" required minlength="50" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('question') }}</textarea></label>
<label class="flex items-center gap-3"><input type="hidden" name="publication_consent" value="0"><input type="checkbox" name="publication_consent" value="1" @checked(old('publication_consent')) class="h-5 w-5 rounded"><span>Kimlik bilgileri gizlenerek yayınlanmasına izin veriyorum.</span></label>
@if($errors->any())<ul class="list-disc rounded-xl border border-rose-400/20 bg-rose-400/10 p-5 pl-9 text-rose-200">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<button class="rounded-xl bg-fuchsia-300 px-6 py-3 font-black text-slate-950">Mektubu gönder</button>
</form></section>
</x-layouts.app>
