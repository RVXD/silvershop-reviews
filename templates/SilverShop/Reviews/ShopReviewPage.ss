<!DOCTYPE html>
<html lang="$ContentLocale">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><%t SilverShop\Reviews.ShopReviewTitle "Review our shop" %> &middot; $SiteTitle</title>
    $ShopConfig.ShopRatingSchemaOrg
    <style>
        body{margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.5}
        .wrap{max-width:640px;margin:40px auto;background:#fff;border-radius:8px;padding:32px 36px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        h1{margin:0 0 4px;font-size:22px}
        h2{margin:1.5rem 0 .5rem;font-size:18px}
        .summary{display:flex;align-items:center;gap:.6rem;margin:.5rem 0 1rem;flex-wrap:wrap}
        .avg{font-size:1.8rem;font-weight:700}
        .stars{position:relative;display:inline-block;font-size:1.2rem;line-height:1;white-space:nowrap}
        .stars-off{color:#d0d0d0}.stars-on{position:absolute;top:0;left:0;overflow:hidden;color:#f5a623}
        .count{color:#666}
        .review{border-top:1px solid #eee;padding:.9rem 0}
        .review__stars{color:#f5a623;letter-spacing:2px}
        .review__verified{margin-left:.5rem;font-size:.8rem;color:#2e7d32;font-weight:600}
        .review__meta{margin:.2rem 0;font-size:.8rem;color:#888}
        .review__body{white-space:pre-line}
        label{display:block;font-weight:bold;margin:.6rem 0 .2rem;font-size:14px}
        input[type=text],textarea,select{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:14px}
        button{background:#f5a623;color:#222;border:0;font-weight:bold;padding:11px 22px;border-radius:4px;cursor:pointer;font-size:14px;margin-top:1rem}
        .message{padding:10px 14px;border-radius:4px;margin:1rem 0}
        .message.good{background:#e6f4ea;color:#1e7e34}.message.bad,.message.error,.message.required{background:#fdecea;color:#b71c1c}
    </style>
</head>
<body>
    <div class="wrap">
        <h1>$SiteTitle</h1>
        <h2><%t SilverShop\Reviews.ReviewOurShop "Review our shop" %></h2>

        <% with $ShopConfig %>
            <% if $ProviderShopRating %>
                <% with $ProviderShopRating %>
                    <p style="color:#555;font-size:.9rem;margin:.25rem 0 1rem;">
                        <span style="color:#f5a623;letter-spacing:1px;">$StarsString</span>
                        <strong>$RatingValue</strong>/5
                        &middot; <%t SilverShop\Reviews.ProviderCount "{count} reviews on {provider}" count=$ReviewCount provider=$ProviderName %><% if $Url %>
                        &middot; <a href="$Url" rel="nofollow noopener" target="_blank"><%t SilverShop\Reviews.ViewOnProvider "view" %></a><% end_if %>
                    </p>
                <% end_with %>
            <% end_if %>
            <% if $HasShopReviews %>
                <div class="summary">
                    <span class="avg">$ShopAverageRating</span><span>/ 5</span>
                    <span class="stars" aria-hidden="true"><span class="stars-off">★★★★★</span><span class="stars-on" style="width:{$ShopRatingPercent}%">★★★★★</span></span>
                    <span class="count"><%t SilverShop\Reviews.ShopCount "{count} shop review(s)" count=$ShopRatingCount %></span>
                </div>
                <% loop $ApprovedShopReviews.Limit(5) %>
                    <article class="review">
                        <span class="review__stars">$StarsString</span>
                        <% if $Verified %><span class="review__verified"><%t SilverShop\Reviews.VerifiedCustomer "✓ Verified customer" %></span><% end_if %>
                        <% if $Title %><p class="review__meta"><strong>$Title</strong></p><% end_if %>
                        <p class="review__meta">$AuthorName &middot; $Created.Nice</p>
                        <div class="review__body">$Content</div>
                    </article>
                <% end_loop %>
            <% end_if %>
        <% end_with %>

        <h2><%t SilverShop\Reviews.LeaveShopReview "Leave a review" %></h2>
        <% if $CanReview %>
            $ReviewForm
        <% else %>
            <p>$GateMessage</p>
        <% end_if %>
    </div>
</body>
</html>
