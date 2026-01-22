<ul {$AttributesHTML}>
    <% loop $Options %>
        <li class="{$Class} <% if $Value != 0 && $Top.getColour($Value).IsThemeColour %>theme-colour<% end_if %>">
            <input id="{$ID}" class="radio" name="{$Name}" type="radio" value="{$Value}" <% if $isChecked %>checked<% end_if %> data-brightness="{$Top.getColour($Value).Brightness}"/>

            <label for="{$ID}" title="<% if $Value != 0 && $Top.getColour($Value).Title %>{$Top.getColour($Value).Title}<% else %>None<% end_if %>" <% if $Value != 0 %>style="background-color: #{$Title};"<% end_if %>>
                <% if $Value != 0 && $Top.getColour($Value).Title %>
                    <div></div>
                    <div></div>
                    <span>
                        <% with $Top.getColour($Value) %>
                            <% if $IsThemeColour %>
                                {$CSSName}
                            <% else %>
                                {$Title}
                            <% end_if %>
                        <% end_with %>
                        <br>
                        <small>#{$Title}</small>
                    </span>
                <% end_if %>
            </label>
        </li>
    <% end_loop %>
</ul>
