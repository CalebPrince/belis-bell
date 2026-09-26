<?php if (is_mock_mode()) : ?>
<div class="bg-navybg text-white text-sm">
  <div class="mx-auto flex max-w-[1280px] flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-2">
    <p>Sample offer: free delivery over GH₵ 800 in Greater Accra. <span class="opacity-80">(mock figure)</span></p>
    <p class="font-semibold">Sample store: products, prices and figures are mock data.</p>
  </div>
</div>
<?php endif; ?>
<header class="border-b border-line bg-surface">
  <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-4 px-4 py-3">
    <a href="/" class="flex items-center gap-3" aria-label="Belis Bell home">
      <img src="<?= asset('brand/logo-mark.webp') ?>" alt="" width="48" height="48" class="h-12 w-12 rounded-lg bg-white object-contain p-0.5">
      <span class="font-display text-2xl font-bold leading-none"><span class="text-navy">Belis</span> <span class="text-leaf">Bell</span></span>
    </a>
    <form class="order-3 w-full flex-1 md:order-none md:w-auto" role="search" aria-label="Search products">
      <label for="q" class="sr-only">Search products</label>
      <input id="q" type="search" disabled placeholder="Search products (not built yet)"
             class="h-12 w-full rounded-xl border border-line bg-canvas px-4 text-base placeholder:text-muted">
    </form>
    <div class="ml-auto flex items-center gap-2 md:ml-0">
      <button type="button" disabled class="btn-quiet">Account</button>
      <button type="button" disabled class="btn-quiet">Cart (0)</button>
    </div>
  </div>
  <nav class="border-t border-line" aria-label="Main">
    <ul class="mx-auto flex max-w-[1280px] flex-wrap gap-x-6 gap-y-1 px-4 py-2 text-sm font-semibold">
      <li><a class="nav-link" href="#categories">Shop products</a></li>
      <li><a class="nav-link" href="#ways">Buy for business</a></li>
      <li><a class="nav-link" href="#products">Top sellers</a></li>
    </ul>
  </nav>
</header>
