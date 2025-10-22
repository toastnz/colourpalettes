<ul {$AttributesHTML}>
    <% loop $Options %>
        <li class="{$Class} <% if $Value != 0 && $Top.getColour($Value).isThemeColour %>theme-colour<% end_if %>">
            <input id="{$ID}" class="radio" name="{$Name}" type="radio" value="{$Value}" <% if $isChecked %>checked<% end_if %> data-brightness="{$Top.getColour($Value).Brightness}"/>

            <label for="{$ID}" title="<% if $Value != 0 && $Top.getColour($Value).Title %>{$Top.getColour($Value).Title}<% else %>None<% end_if %>" <% if $Value != 0 %>style="background-color: #{$Title};"<% end_if %>>
                <% if $Value != 0 && $Top.getColour($Value).Title %>
                    <div></div>
                    <div></div>
                    <span>{$Top.getColour($Value).Title}</span>
                <% end_if %>
            </label>
        </li>
    <% end_loop %>
</ul>
