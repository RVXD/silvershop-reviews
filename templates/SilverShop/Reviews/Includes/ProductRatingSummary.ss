<%-- Compact product rating snippet for placing under the product title; links down to the reviews section.
     Renders nothing until there is at least one approved review. --%>
<% if $HasReviews %>
    <style>
        .product-rating-summary{display:flex;width:fit-content;align-items:center;gap:.4rem;text-decoration:none;color:inherit;margin:.25em 0 1.25em}
        .product-rating-summary__stars{position:relative;display:inline-block;font-size:1.05em;line-height:1;white-space:nowrap}
        .product-rating-summary__stars .off{color:#d0d0d0}
        .product-rating-summary__stars .on{position:absolute;top:0;left:0;overflow:hidden;color:#f5a623}
        .product-rating-summary__count{color:#666;text-decoration:underline}
    </style>
    <a class="product-rating-summary" href="#reviews" title="<%t SilverShop\Reviews.ReadReviews "Read the reviews" %>">
        <span class="product-rating-summary__stars" aria-hidden="true"><span class="off">★★★★★</span><span class="on" style="width:{$AverageRatingPercent}%">★★★★★</span></span>
        <strong>$AverageRating</strong>
        <span class="product-rating-summary__count"><%t SilverShop\Reviews.SummaryCount "{count} review|{count} reviews" count=$RatingCount %></span>
    </a>
<% end_if %>
