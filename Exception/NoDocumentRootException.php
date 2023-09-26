<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Exception;

class NoDocumentRootException extends LocalizedException
{
    public const PHRASE = 'Document root not found.';

    /**
     * @param Phrase|null $phrase
     * @param Exception|null $cause
     * @param int $code
     */
    public function __construct(
        Phrase $phrase = null,
        Exception $cause = null,
        $code = 0
    ) {
        $message = self::PHRASE;
        if ($phrase === null) {
            $phrase = new Phrase(__($message));
        }
        parent::__construct($phrase, $cause, $code);
    }
}
