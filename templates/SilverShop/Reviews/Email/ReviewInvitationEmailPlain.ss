<% if $SiteConfig.StoreProfileName %>$SiteConfig.StoreProfileName<% else %>$SiteConfig.Title<% end_if %>

<% if $IsReminder %><%t SilverShop\Reviews.EmailReminderTitle "A reminder to review your order" %><% else %><%t SilverShop\Reviews.EmailTitle "Review your order" %><% end_if %>

<% if $IsReminder %><%t SilverShop\Reviews.EmailReminderIntro "A quick reminder — we'd still love to hear what you think of your recent order." %><% else %><%t SilverShop\Reviews.EmailIntro "Thanks for shopping with us! We'd love to hear what you think." %><% end_if %>
<%t SilverShop\Reviews.EmailAsk "Please take a moment to review the products you bought:" %>
<% loop $Products %>- $Title
<% end_loop %>
$ReviewLink

<%t SilverShop\Reviews.EmailOptOutLead "Not interested?" %> <%t SilverShop\Reviews.EmailOptOutLink "Don't send me review invitations for this order" %>:
$UnsubscribeLink
