<?php

namespace App\Domains\Creative\Requests;

/**
 * Validates studio parameters for purchasing a compiled prompt (Track 2, spec item 13).
 *
 * Inherits all validation rules and messages from PreviewCreativeRequest to
 * guarantee 1:1 parameter parity with the live studio preview.
 */
class CompilePromptRequest extends PreviewCreativeRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
