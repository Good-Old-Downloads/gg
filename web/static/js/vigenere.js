/*
    The MIT License (MIT)

    Copyright (c) 2015 Jeremias Menichelli

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
*/

/*
    Original Source: https://github.com/jeremenichelli/vigenere/blob/master/dist/vigenere.js
    Slightly Modified by TwelveCharzz
*/

var lowerReference = 'abcdefghijklmnopqrstuvwxyz';
var upperReference = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/**
 * @method isalpha
 * @param {String} str string to test
 * @return {Boolean} returns true if is a letter
 */
function isalpha(str) {
    return (/^[a-zA-Z]+$/).test(str);
}

/**
 * Applies Vigenère encryption to a phrase given a word and a
 * numeric flag passed as a the third argument, when flag is
 * positive it ciphers, when negative it deciphers
 * @method process
 * @param {String} word string to process
 * @param {String} phrase secret key
 * @param {Number} flag decides action
 * @return {String} result process output
 */
function process(word, phrase, flag) {
    if (typeof(flag) === 'undefined') {
        flag = 1;
    }
    // check if arguments are correct
    if (typeof word !== 'string' || typeof phrase !== 'string') {
        throw new Error('vignere: key word and phrase must be strings');
    }

    // throw error if word is not valid
    if (!isalpha(word)) {
        throw new Error('vignere: key word can only contain letters');
    }

    // pass key word all to lower case
    word = word.toLowerCase();

    var len = phrase.length;
    var wlen = word.length;

    var i = 0,
        wi = 0,
        ci,
        pos,
        result = '';

    for (; i < len; i++) {
        pos = phrase[i];
        if (isalpha(pos)) {
            if (flag > 0) {
                ci = lowerReference.indexOf(pos.toLowerCase()) + lowerReference.indexOf(word[wi]);
            } else {
                ci = lowerReference.indexOf(pos.toLowerCase()) - lowerReference.indexOf(word[wi]);
                ci = ci < 0 ? 26 + ci : ci;
            }
            ci %= 26;
            // take cipher from lower or upper reference
            result = lowerReference.indexOf(pos) === -1 ? result + upperReference[ci] : result + lowerReference[ci];
            // reset word index when it exceeds word length
            wi = wi + 1 === wlen ? 0 : wi + 1;
        } else {
            result += pos;
        }
    }

    return result;
}

function cipher(w, p) {
    return process(w, p);
}

function decipher(w, p) {
    return process(w, p, -1);
}