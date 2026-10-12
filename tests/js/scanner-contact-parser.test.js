import assert from 'node:assert/strict';
import test from 'node:test';

import { extractBackContacts } from '../../resources/js/scanner-contact-parser.js';

test('extracts a labeled student contact and a separate emergency contact', () => {
    const result = extractBackContacts(`Contact Number
09171234567
Emergency Contact: Caroline M. Bartolay
09756326393`);

    assert.equal(result.contact, '09171234567');
    assert.equal(result.emergency, 'Caroline M. Bartolay · 09756326393');
});

test('repairs common OCR character substitutions in Philippine mobile numbers', () => {
    const result = extractBackContacts(`Contact No. O9I7-123-4567
In case of emergency
Caroline Bartolay
09756326393`);

    assert.equal(result.contact, '09171234567');
    assert.equal(result.emergency, 'Caroline Bartolay · 09756326393');
});

test('extracts a number following a plain contact label', () => {
    const result = extractBackContacts(`Contact: 09171234567
Emergency contact: Caroline Bartolay 09756326393`);

    assert.equal(result.contact, '09171234567');
    assert.equal(result.emergency, 'Caroline Bartolay · 09756326393');
});

test('does not report the emergency number as the student contact when it is the only number', () => {
    const result = extractBackContacts(`Emergency contact
Caroline M. Bartolay
09756326393`);

    assert.equal(result.contact, '');
    assert.equal(result.emergency, 'Caroline M. Bartolay · 09756326393');
});

test('leaves ambiguous unlabeled phone numbers empty instead of guessing', () => {
    const result = extractBackContacts(`09171234567
09281234567`);

    assert.equal(result.contact, '');
});

test('extracts a contact number split across OCR lines', () => {
    const result = extractBackContacts(`Contact Number
0917
123
4567
Emergency contact
Caroline Bartolay
09756326393`);

    assert.equal(result.contact, '09171234567');
    assert.equal(result.emergency, 'Caroline Bartolay · 09756326393');
});

test('normalizes international contact numbers to local format', () => {
    const result = extractBackContacts(`Contact Number: +63 917 123 4567
Emergency contact: Caroline Bartolay 09756326393`);

    assert.equal(result.contact, '09171234567');
});
