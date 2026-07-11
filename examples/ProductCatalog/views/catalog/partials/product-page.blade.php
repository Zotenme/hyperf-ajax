@forelse ($catalog['items'] as $product)
    <article data-product-id="{{ $product['id'] }}">
        <small>{{ ucfirst($product['category']) }}</small>
        <h2>{{ $product['name'] }}</h2>
        <strong>${{ number_format($product['price'], 2) }}</strong>
    </article>
@empty
    @if ($catalog['page'] === 1)
        <p>No products match these filters.</p>
    @endif
@endforelse
