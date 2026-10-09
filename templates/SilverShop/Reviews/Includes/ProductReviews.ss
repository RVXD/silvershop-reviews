<section class="product-reviews" id="reviews">
    <style>
        .product-reviews{margin:2rem 0;border-top:1px solid #e2e2e2;padding-top:1.5rem}
        .product-reviews h2{margin:0 0 1rem}
        .product-reviews__summary{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap}
        .product-reviews__avg{font-size:2em;font-weight:700;line-height:1}
        .product-reviews__stars{position:relative;display:inline-block;font-size:1.25em;line-height:1;white-space:nowrap}
        .product-reviews__stars-off{color:#d0d0d0}
        .product-reviews__stars-on{position:absolute;top:0;left:0;overflow:hidden;color:#f5a623}
        .product-reviews__count{color:#666}
        .product-reviews__breakdown{list-style:none;margin:0 0 1.5rem;padding:0;max-width:320px}
        .product-reviews__breakdown li{display:flex;align-items:center;gap:.5rem;font-size:.9em;color:#666}
        .product-reviews__bar{flex:1;height:8px;background:#eee;border-radius:4px;overflow:hidden}
        .product-reviews__bar span{display:block;height:100%;background:#f5a623}
        .review{border-top:1px solid #eee;padding:1rem 0}
        .review__stars{color:#f5a623;letter-spacing:2px}
        .review__verified{margin-left:.5rem;font-size:.85em;color:#2e7d32;font-weight:600}
        .review__title{margin:.4rem 0 .2rem;font-size:1.1em}
        .review__meta{margin:0 0 .4rem;font-size:.85em;color:#888}
        .review__body{white-space:pre-line}
        .review__photos{display:flex;gap:.4rem;margin:.6rem 0;flex-wrap:wrap}
        .review__photos img{border-radius:4px;object-fit:cover}
        .review__helpful{margin:.5rem 0 0;font-size:.85em;color:#777;display:flex;align-items:center;gap:.5rem}
        .review__vote{display:inline;margin:0}
        .review__vote button{background:none;color:#555;border:1px solid #ccc;border-radius:4px;padding:1px 8px;font-size:.85em;cursor:pointer}
        .product-reviews__pagination{display:flex;gap:.3rem;align-items:center;margin:1rem 0;flex-wrap:wrap}
        .product-reviews__pagination a,.product-reviews__pagination strong,.product-reviews__pagination span{padding:2px 9px;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#555;font-size:.9em}
        .product-reviews__pagination strong{background:#f5a623;border-color:#f5a623;color:#222}
        .product-reviews__form{margin-top:1.5rem;max-width:520px}
        .product-qna{margin-top:2rem;border-top:1px solid #e2e2e2;padding-top:1.25rem}
        .qna__item{border-top:1px solid #eee;padding:.8rem 0}
        .qna__q{font-weight:600}
        .qna__a{margin:.3rem 0 0 1rem;padding-left:.6rem;border-left:2px solid #eee}
        .qna__staff{font-size:.8em;color:#2e7d32;font-weight:600;margin-left:.4rem}
    </style>

    <% if $ReviewsEnabled %>
    <h2><%t SilverShop\Reviews.Heading "Reviews" %></h2>

    <% if $ProviderRating %>
        <% with $ProviderRating %>
            <p class="product-reviews__provider" style="color:#555;font-size:.9em;margin:.25rem 0 1rem;">
                <span style="color:#f5a623;letter-spacing:1px;">$StarsString</span>
                <strong>$RatingValue</strong>/5
                &middot; <%t SilverShop\Reviews.ProviderCount "{count} reviews on {provider}" count=$ReviewCount provider=$ProviderName %><% if $Url %>
                &middot; <a href="$Url" rel="nofollow noopener" target="_blank"><%t SilverShop\Reviews.ViewOnProvider "view" %></a><% end_if %>
            </p>
        <% end_with %>
    <% end_if %>

    <% if $HasReviews %>
        <div class="product-reviews__summary">
            <span class="product-reviews__avg">$AverageRating</span>
            <span>/ 5</span>
            <span class="product-reviews__stars" aria-hidden="true">
                <span class="product-reviews__stars-off">★★★★★</span>
                <span class="product-reviews__stars-on" style="width:{$AverageRatingPercent}%">★★★★★</span>
            </span>
            <span class="product-reviews__count"><%t SilverShop\Reviews.Count "{count} review(s)" count=$RatingCount %></span>
        </div>

        <ul class="product-reviews__breakdown">
            <% loop $RatingBreakdown %>
                <li>
                    <span>$Stars★</span>
                    <span class="product-reviews__bar"><span style="width:{$Percent}%"></span></span>
                    <span>$Count</span>
                </li>
            <% end_loop %>
        </ul>

        $ReviewsSchemaOrg
    <% else %>
        <p><%t SilverShop\Reviews.None "No reviews yet. Be the first to review this product." %></p>
    <% end_if %>

    <% loop $PaginatedReviews %>
        <article class="review">
            <header>
                <span class="review__stars" aria-label="$Rating / 5">$StarsString</span>
                <% if $Verified %><span class="review__verified"><%t SilverShop\Reviews.Verified "✓ Verified purchase" %></span><% end_if %>
            </header>
            <% if $Title %><h3 class="review__title">$Title</h3><% end_if %>
            <p class="review__meta">$AuthorName &middot; $Created.Nice</p>
            <div class="review__body">$Content</div>

            <% if $HasImages %>
                <div class="review__photos">
                    <% loop $Images %><% if $Thumbnail %>$Thumbnail<% end_if %><% end_loop %>
                </div>
            <% end_if %>

            <% if $VotesEnabled %>
                <p class="review__helpful">
                    <span><%t SilverShop\Reviews.Helpful "Was this helpful?" %></span>
                    <form method="post" action="$VoteLink('up')" class="review__vote">$VoteSecurityField<button type="submit">&#128077; $HelpfulUp</button></form>
                    <form method="post" action="$VoteLink('down')" class="review__vote">$VoteSecurityField<button type="submit">&#128078; $HelpfulDown</button></form>
                </p>
            <% end_if %>
        </article>
    <% end_loop %>

    <% with $PaginatedReviews %>
        <% if $MoreThanOnePage %>
            <nav class="product-reviews__pagination" aria-label="Reviews pages">
                <% if $NotFirstPage %><a href="{$PrevLink}#reviews" rel="prev">&laquo;</a><% end_if %>
                <% loop $PaginationSummary(4) %>
                    <% if $CurrentBool %><strong>$PageNum</strong><% else_if $Link %><a href="{$Link}#reviews">$PageNum</a><% else %><span>&hellip;</span><% end_if %>
                <% end_loop %>
                <% if $NotLastPage %><a href="{$NextLink}#reviews" rel="next">&raquo;</a><% end_if %>
            </nav>
        <% end_if %>
    <% end_with %>

    <div class="product-reviews__form">
        <% if $CanReview %>
            <% if $CollapseReviewForm %><input type="submit" class="action" data-review-toggle value="<%t SilverShop\Reviews.WriteHeading 'Write a review' %>" hidden /><% end_if %>
            <div class="product-reviews__formbody"<% if $CollapseReviewForm %> data-review-formbody<% end_if %>>
                <h3><%t SilverShop\Reviews.WriteHeading "Write a review" %></h3>
                $ReviewForm
            </div>
            <% if $CollapseReviewForm %><% include SilverShop\Reviews\ReviewFormToggle %><% end_if %>
        <% else %>
            <p class="review-gate">$ReviewGateMessage</p>
        <% end_if %>
    </div>
    <% end_if %>

    <% if $QnaEnabled %>
    <div class="product-qna" id="questions">
        <h2><%t SilverShop\Reviews.QnaHeading "Questions &amp; answers" %></h2>

        <% if $HasQuestions %>
            <% loop $ApprovedQuestions %>
                <div class="qna__item">
                    <p class="qna__q">$Question</p>
                    <% loop $ApprovedAnswers %>
                        <div class="qna__a">$Answer<% if $IsStaff %><span class="qna__staff"><%t SilverShop\Reviews.StaffAnswer "Shop" %></span><% end_if %></div>
                    <% end_loop %>
                </div>
            <% end_loop %>
        <% else %>
            <p><%t SilverShop\Reviews.NoQuestions "No questions yet. Ask the first one." %></p>
        <% end_if %>

        <% if $CanAsk %>
            <div class="product-reviews__form">
                <h3><%t SilverShop\Reviews.AskHeading "Ask a question" %></h3>
                $QuestionForm
            </div>
        <% end_if %>
    </div>
    <% end_if %>
</section>
