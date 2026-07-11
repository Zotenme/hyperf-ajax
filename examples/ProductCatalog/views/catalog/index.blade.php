<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product catalog</title>
    <script src="/vendor/hyperfajax/framework-bundle.min.js"></script>
    <style>
        body { max-width: 920px; margin: 2rem auto; font-family: sans-serif; }
        form.filters { display: flex; gap: 1rem; align-items: end; margin-bottom: 1.5rem; }
        label { display: grid; gap: .35rem; }
        #product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        article { padding: 1rem; border: 1px solid #ddd; border-radius: .5rem; }
        #load-more { margin-top: 1rem; text-align: center; }
    </style>
</head>
<body>
    <h1>Product catalog</h1>

    <form
        class="filters"
        data-request="onFilter"
        data-request-update='{"catalog/product-page":"#product-grid","catalog/load-more":"!#load-more","catalog/summary":"!#catalog-summary"}'
    >
        <label>
            Category
            <select name="category">
                <option value="">All categories</option>
                <option value="office">Office</option>
                <option value="home">Home</option>
                <option value="travel">Travel</option>
            </select>
        </label>

        <label>
            Minimum price
            <input name="min_price" type="number" min="0" value="0">
        </label>

        <button type="submit">Apply filters</button>
    </form>

    @include('catalog.partials.summary', ['catalog' => $catalog])

    <div id="product-grid">
        @include('catalog.partials.product-page', ['catalog' => $catalog])
    </div>

    @include('catalog.partials.load-more', ['catalog' => $catalog])
</body>
</html>
