<x-error-page :code="$exception?->getStatusCode() ?? 400" mode="sweep" title="That didn't work.">
    The request couldn't be completed. Check the link, or start again from the home page.
</x-error-page>
