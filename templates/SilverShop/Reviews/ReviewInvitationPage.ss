<!DOCTYPE html>
<html lang="$ContentLocale">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><%t SilverShop\Reviews.LandingTitle "Review your order" %> &middot; $SiteTitle</title>
    <style>
        body{margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.5}
        .wrap{max-width:620px;margin:40px auto;background:#fff;border-radius:8px;padding:32px 36px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        h1{margin:0 0 4px;font-size:22px}
        h2{margin:1.5rem 0 .5rem;font-size:18px}
        h3{margin:1.5rem 0 .4rem;font-size:15px;border-top:1px solid #eee;padding-top:1rem}
        .muted{color:#888;font-size:13px;margin-top:2rem}
        .muted a{color:#888}
        label{display:block;font-weight:bold;margin:.6rem 0 .2rem;font-size:14px}
        input[type=text],textarea,select{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:14px}
        .btn-toolbar button,button{background:#f5a623;color:#222;border:0;font-weight:bold;padding:11px 22px;border-radius:4px;cursor:pointer;font-size:14px;margin-top:1rem}
        .message{padding:10px 14px;border-radius:4px;margin:1rem 0}
        .message.good{background:#e6f4ea;color:#1e7e34}
        .message.bad,.message.error,.message.required{background:#fdecea;color:#b71c1c}
        .field{margin-bottom:.4rem}
    </style>
</head>
<body>
    <div class="wrap">
        <h1>$SiteTitle</h1>

        <% if $IsUnsubscribed %>
            <h2><%t SilverShop\Reviews.Unsubscribed "You're unsubscribed" %></h2>
            <p><%t SilverShop\Reviews.UnsubMsg "You won't receive review invitations for this order anymore." %></p>
        <% else_if $IsResponded %>
            <h2><%t SilverShop\Reviews.ThanksHeading "Thank you!" %></h2>
            <p><%t SilverShop\Reviews.AlreadyReviewed "You've reviewed this order. Thanks for sharing your feedback." %></p>
        <% else %>
            <h2><%t SilverShop\Reviews.ReviewYourOrder "Review your order" %></h2>
            <p><%t SilverShop\Reviews.LandingIntro "Tell other shoppers what you think of the products you bought." %></p>
            <% if $ReviewForm %>
                $ReviewForm
            <% else %>
                <p><%t SilverShop\Reviews.NothingToReview "There's nothing to review for this order." %></p>
            <% end_if %>
            <p class="muted"><a href="$Invitation.UnsubscribeLink"><%t SilverShop\Reviews.DontEmail "Don't send me review invitations" %></a></p>
        <% end_if %>
    </div>
</body>
</html>
