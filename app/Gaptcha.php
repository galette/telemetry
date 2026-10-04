<?php

namespace GaletteTelemetry;

use NumberFormatter;

class Gaptcha
{
    public const OP_ADD = 1;
    public const OP_SUB = 2;

    private int $max = 12;
    private int $min = 1;

    /** @var integer */
    private int $current_left;
    /** @var integer */
    private int $current_right;
    /** @var integer */
    private int $current_op;
    /** @var integer */
    private int $gaptcha;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->current_left = random_int($this->min, $this->max);
        $this->current_right = random_int($this->min, $this->max);
        $this->current_op = random_int(1, 2);
        switch ($this->current_op) {
            case self::OP_ADD:
                $this->gaptcha = $this->current_left + $this->current_right;
                break;
            case self::OP_SUB:
                $this->gaptcha = $this->current_left - $this->current_right;
                break;
        }
    }

    /**
     * Get questions phrase
     *
     * @return string
     */
    public function getQuestion(): string
    {
        $add_questions = [
            'How much is %1$s plus %2$s?',
            'How much is %1$s added to %2$s?',
            'I have %1$s Galettes, a friend give me %2$s more. How many Galettes do I have?'
        ];
        $sub_questions = [
            'How much is %1$s minus %2$s?',
            'How much is %1$s on which we retire %2$s?',
            'How much is %2$s retired to %1$s?',
            'I have %1$s Galettes, I give %2$s of them. How many Galettes do I have?'
        ];

        $questions = ($this->current_op === self::OP_ADD) ? $add_questions : $sub_questions;
        return $questions[random_int(0, (count($questions) - 1))];
    }


    /**
     * Generate captcha question to display
     *
     * @return string
     */
    public function generateQuestion(): string
    {
        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        return sprintf(
            $this->getQuestion(),
            $formatter->format($this->current_left),
            $formatter->format($this->current_right)
        );
    }

    /**
     * Get expected answer
     *
     * @return integer
     */
    public function getAnswer(): int
    {
        return $this->gaptcha;
    }

    /**
     * Checks captcha validity against the answer stored in session.
     * Stored answer is removed, so a captcha can be checked only once.
     *
     * @param mixed $gaptcha User entry
     *
     * @return boolean
     */
    public static function checkSession(mixed $gaptcha): bool
    {
        $expected = $_SESSION['gaptcha'] ?? null;
        unset($_SESSION['gaptcha']);

        return is_int($expected)
            && is_numeric($gaptcha)
            && (int)$gaptcha === $expected;
    }
}
