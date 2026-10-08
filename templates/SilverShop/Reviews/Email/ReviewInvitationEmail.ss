<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><%t SilverShop\Reviews.EmailTitle "Review your order" %></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#333;">
                    <tr>
                        <td style="background:#222;color:#fff;padding:20px 28px;font-size:18px;font-weight:bold;">$ShopName</td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <% if $IsReminder %>
                                <p style="margin:0 0 14px;font-size:16px;">A quick reminder &mdash; we'd still love to hear what you think of your recent order.</p>
                            <% else %>
                                <p style="margin:0 0 14px;font-size:16px;">Thanks for shopping with us! We'd love to hear what you think.</p>
                            <% end_if %>

                            <p style="margin:0 0 10px;">Please take a moment to review the product<% if $Products.Count > 1 %>s<% end_if %> you bought:</p>
                            <ul style="margin:0 0 22px;padding-left:20px;">
                                <% loop $Products %><li style="margin:4px 0;">$Title</li><% end_loop %>
                            </ul>

                            <p style="margin:0 0 26px;">
                                <a href="$ReviewLink" style="display:inline-block;background:#f5a623;color:#222;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:4px;">Write your review</a>
                            </p>

                            <p style="margin:0;font-size:12px;color:#999;">
                                Not interested? <a href="$UnsubscribeLink" style="color:#999;">Don't send me review invitations for this order</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
