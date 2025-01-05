<ul $AttributesHTML>
	<% loop $Options %>
        <li class="{$Class}">
            <input id="{$ID}" class="radio" name="{$Name}" type="radio" value="{$Value}"<% if $Top.isChecked($Value) %> checked<% end_if %> data-brightness="{$Top.getColour($Value).ColourBrightness}"/>
            <label for="{$ID}" title="" style="background-color: {$Top.getColour($Value).ColourValue}">
                <div></div>
                <div></div>
                <span>{$Top.getColour($Value).Title}</span>
            </label>
        </li>
	<% end_loop %>
</ul>
