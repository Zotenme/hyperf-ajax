<div id="load-more">
    @if ($catalog['nextPage'] !== null)
        <form>
            <input type="hidden" name="page" value="{{ $catalog['nextPage'] }}">
            <input type="hidden" name="category" value="{{ $catalog['filters']['category'] }}">
            <input type="hidden" name="min_price" value="{{ $catalog['filters']['minPrice'] }}">
            <button
                type="button"
                data-request="onLoadMore"
                data-request-update='{"catalog/product-page":"@#product-grid","catalog/load-more":"!#load-more","catalog/summary":"!#catalog-summary"}'
            >Show more</button>
        </form>
    @endif
</div>
