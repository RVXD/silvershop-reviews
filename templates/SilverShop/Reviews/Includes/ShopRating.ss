<% if $SiteConfig.HasShopReviews %>
    <div class="shop-rating" style="display:inline-flex;align-items:center;gap:.4rem;font-family:Arial,Helvetica,sans-serif;font-size:14px;">
        <style>
            .shop-rating__stars{position:relative;display:inline-block;font-size:1.05em;line-height:1;white-space:nowrap}
            .shop-rating__stars .off{color:#d0d0d0}
            .shop-rating__stars .on{position:absolute;top:0;left:0;overflow:hidden;color:#f5a623}
            .shop-rating a{color:inherit;text-decoration:none}
        </style>
        <a href="{$BaseHref}shop-review" title="<%t SilverShop\Reviews.ReadShopReviews "Read our shop reviews" %>">
            <span class="shop-rating__stars" aria-hidden="true"><span class="off">★★★★★</span><span class="on" style="width:{$SiteConfig.ShopRatingPercent}%">★★★★★</span></span>
            <strong>$SiteConfig.ShopAverageRating</strong>/5
            <span>(<%t SilverShop\Reviews.ShopCountShort "{count} reviews" count=$SiteConfig.ShopRatingCount %>)</span>
        </a>
    </div>
<% end_if %>
