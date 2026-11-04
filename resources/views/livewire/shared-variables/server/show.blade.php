<div>
    <x-slot:title>
        Server Variables | Dashy
    </x-slot>

    <x-shared-variables.editor :resource="$server"
        :variables="$server->environment_variables"
        :readOnlyKeys="['DASHY_SERVER_UUID', 'DASHY_SERVER_NAME']"
        type="server" title="{{ $server->name }}"
        :view="$view" variablesLabel="Server shared variables" />
</div>
