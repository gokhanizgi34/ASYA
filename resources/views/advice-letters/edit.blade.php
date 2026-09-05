<x-layouts.app title="Mukaddes Abla · Yanıt">
<section class="mx-auto max-w-4xl"><header><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">MUKADDES ABLA</p><h1 class="mt-3 text-4xl font-black">Mektubu yanıtla</h1><p class="mt-3 whitespace-pre-line text-slate-300">{{ $letter->question }}</p></header>
<form method="POST" action="{{ route('advice-letters.update',$letter) }}" class="mt-8 space-y-5 rounded-2xl border border-white/10 bg-white/[.04] p-6">
@csrf
@method('PUT')
<label class="block"><span class="mb-2 block font-bold">Durum</span><select name="status" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">@foreach(App\AdviceLetterStatus::cases() as $status)<option value="{{ $status->value }}" @selected(old('status',$letter->status->value)===$status->value)>{{ $status->label() }}</option>@endforeach</select></label>
<label class="block"><span class="mb-2 block font-bold">Yanıt başlığı</span><input name="response_title" value="{{ old('response_title',$letter->response_title) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
<label class="block"><span class="mb-2 block font-bold">Yanıt</span><textarea name="response_body" rows="10" required minlength="100" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('response_body',$letter->response_body) }}</textarea></label>
@if($errors->any())<ul class="list-disc rounded-xl border border-rose-400/20 bg-rose-400/10 p-5 pl-9 text-rose-200">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<button class="rounded-xl bg-fuchsia-300 px-6 py-3 font-black text-slate-950">Yanıtı kaydet</button>
</form></section>
</x-layouts.app>
