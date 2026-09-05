<x-layouts.app title="Haber oluştur">
	<section class="mx-auto max-w-4xl space-y-10">
		<header><p class="text-sm font-bold tracking-[.18em] text-cyan-300">HABER MERKEZİ</p><h1 class="mt-3 text-4xl font-black">Yeni haber</h1><p class="mt-2 text-slate-400">Konudan otomatik üretin veya içeriği elle hazırlayın.</p></header>
		@if(auth()->user()->isSystemAdministrator() || auth()->user()->isAgencyOwner())
			<section class="border-y border-cyan-300/20 py-7">
				<h2 class="text-2xl font-black text-cyan-200">Konudan AI haber üret</h2>
				<form method="POST" action="{{ route('articles.generate-topic') }}" enctype="multipart/form-data" class="mt-5 grid gap-5 sm:grid-cols-2">
					@csrf
					@if(auth()->user()->isSystemAdministrator())
						<label>Ajans<select name="agency_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"><option value="">Ajans seçin</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(old('agency_id') == $agency->id)>{{ $agency->name }}</option>@endforeach</select></label>
					@else
						<input type="hidden" name="agency_id" value="{{ auth()->user()->agency_id }}">
					@endif
					<label class="sm:col-span-2">Haber konusu<textarea name="topic" required minlength="10" maxlength="1000" rows="4" placeholder="2026 KYK yurt sonuçları ne zaman açıklanacak?" class="mt-1 w-full rounded-xl border border-cyan-300/30 bg-slate-900 px-4 py-3">{{ old('topic') }}</textarea></label>
					<label class="sm:col-span-2">Haber görseli <span class="text-slate-500">(isteğe bağlı)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"></label>
					<label class="flex items-start gap-2 text-sm text-slate-300 sm:col-span-2"><input type="checkbox" name="confirm_image_rights" value="1" class="mt-1"> Görseli kullanma ve yayınlama hakkına sahip olduğumu onaylıyorum.</label>
					@error('topic_generation')<p class="text-sm text-rose-300 sm:col-span-2">{{ $message }}</p>@enderror
					<button class="w-fit rounded-xl bg-cyan-300 px-5 py-3 font-bold text-slate-950 hover:bg-cyan-200 sm:col-span-2">Üret ve Yayın Merkezi'ne gönder</button>
				</form>
			</section>
		@endif
		<div class="rounded-2xl border border-white/10 bg-white/[.04] p-6 sm:p-8"><form method="POST" action="{{ route('articles.store') }}">@csrf @include('articles._form', ['article' => null, 'submitLabel' => 'Haberi kaydet'])</form></div>
	</section>
</x-layouts.app>