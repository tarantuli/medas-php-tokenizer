<?php

// A line comment
# A hash comment

/*
 * A block comment
 */

/**
 * A doc comment
 *
 * @param int $x
 */
function documented(int $x): void // Trailing comment
{
    $y = 1; /* inline block comment */
    $z = [
        // Comment in an array
        1,
        2, // Trailing comment in an array
    ];
}
