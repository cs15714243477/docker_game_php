<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

declare(strict_types=1);

namespace Respect\Validation\Exceptions;

/**
 * @author Henrique Moody <henriquemoody@gmail.com>
 * @author Danilo Correa <danilosilva87@gmail.com>
 * @author Kleber Hamada Sato <kleberhs007@yahoo.com>
 */
final class ConsonantException extends FilteredValidationException
{
    /**
     * {@inheritDoc}
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} 只能包含辅音',
            self::EXTRA => '{{name}} 只能包含辅音和 {{additionalChars}}',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} 不能包含辅音',
            self::EXTRA => '{{name}} 不能包含辅音或 {{additionalChars}}',
        ],
    ];
}
