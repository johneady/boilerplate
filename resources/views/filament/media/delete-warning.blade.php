{{--
    The delete confirmation's body.

    A view rather than a string built in PHP: the copy is two separate
    sentences with different weights, and gluing markup onto __() calls is
    what tests/Feature/TranslationsTest.php forbids (word order is not the
    same in every language). Here each string stands alone.

    Filament gives a confirmation modal the `alertdialog` role and announces
    its description on open, so this is reachable by screen readers -- which
    is why the warning lives in the description and not elsewhere in the modal.
--}}
<div class="space-y-2">
    <p>{{ __('media.delete.description') }}</p>
    <p class="font-semibold">{{ __('media.delete.consequences') }}</p>
</div>
