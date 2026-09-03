<x-layouts.app title="Giriş">
    <section class="mx-auto flex min-h-[75vh] max-w-md items-center">
        <div class="w-full rounded-3xl border border-white/10 bg-white/[.04] p-7 shadow-2xl shadow-black/30 sm:p-9">
            <a href="{{ route('login') }}" class="mb-8 inline-flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-300 font-black text-slate-950">A</span><span class="text-xl font-black tracking-[.18em]">ASYA</span></a>
            <h1 class="text-3xl font-black">Tekrar hoş geldiniz</h1>
            <p class="mt-2 text-slate-400">Yönetim paneline güvenli giriş yapın.</p>
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf
                <div><label for="email" class="mb-2 block text-sm font-semibold">E-posta</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300" /></div>
                <div><label for="password" class="mb-2 block text-sm font-semibold">Parola</label><input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300" /></div>
                <label class="flex items-center gap-3 text-sm text-slate-300"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300" /> Beni hatırla</label>
                <button class="w-full rounded-xl bg-cyan-300 px-5 py-3 font-bold text-slate-950 hover:bg-cyan-200">Giriş yap</button>
            </form>
        </div>
    </section>
</x-layouts.app>