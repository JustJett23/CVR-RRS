const phoneCandidatePattern = /(?:\+?\s*63|00\s*63)?[\s().-]*(?:0[\s().-]*)?9(?:[\s().-]*[0-9OIlSsBbGgQq]){9}/gi;

function normalizePhoneCandidate(value) {
    let compact = value
        .replace(/[Oo]/g, '0')
        .replace(/[Il|]/g, '1')
        .replace(/[Ss]/g, '5')
        .replace(/[Bb]/g, '8')
        .replace(/[GgQq]/g, '9')
        .replace(/\D/g, '');

    if (compact.startsWith('0063')) {
        compact = compact.slice(4);
    } else if (compact.startsWith('63')) {
        compact = compact.slice(2);
    }

    if (compact.length === 10 && compact.startsWith('9')) {
        compact = `0${compact}`;
    }

    return /^09\d{9}$/.test(compact) ? compact : '';
}

function lineAt(text, position) {
    return text.slice(0, position).split('\n').length - 1;
}

export function extractBackContacts(text) {
    const normalizedText = String(text || '').replace(/\r/g, '');
    const lines = normalizedText.split('\n').map((line) => line.trim());
    const emergencyLineIndexes = lines.flatMap((line, index) => (
        /emergency|please\s+notify|in\s+case\s+of|contact\s+person|notify/i.test(line) ? [index] : []
    ));
    const allPhones = [...normalizedText.matchAll(phoneCandidatePattern)]
        .map((match) => ({
            phone: normalizePhoneCandidate(match[0]),
            index: lineAt(normalizedText, match.index),
            endIndex: lineAt(normalizedText, match.index + match[0].length),
        }))
        .filter(({ phone }) => Boolean(phone));
    const emergencyPhones = allPhones.filter(({ index, endIndex }) => (
        emergencyLineIndexes.some((emergencyIndex) => (
            index <= emergencyIndex && endIndex >= emergencyIndex
            || index > emergencyIndex && index <= emergencyIndex + 2
        ))
    ));
    const contactLabelIndex = lines.findIndex((line) => (
        /(?:contact(?:\s*(?:no\.?|number))?|mobile|phone)/i.test(line)
        && !/emergency|notify|contact\s+person/i.test(line)
    ));
    const contactCandidates = contactLabelIndex >= 0
        ? allPhones.filter(({ index }) => index >= contactLabelIndex && index <= contactLabelIndex + 3)
        : allPhones.filter(({ index }) => !emergencyLineIndexes.some((emergencyIndex) => index >= emergencyIndex && index <= emergencyIndex + 2));
    const emergencyPhoneSet = new Set(emergencyPhones.map(({ phone }) => phone));
    const distinctContactCandidates = [...new Set(contactCandidates
        .map(({ phone }) => phone)
        .filter((phone) => !emergencyPhoneSet.has(phone)))];
    const contact = distinctContactCandidates.length === 1 ? distinctContactCandidates[0] : '';

    const emergencyAnchor = emergencyLineIndexes[0] ?? -1;
    const emergencyStartIndex = emergencyAnchor >= 0 ? Math.max(0, emergencyAnchor - 1) : lines.length;
    const emergencyEndIndex = emergencyAnchor >= 0 ? Math.min(lines.length, emergencyAnchor + 4) : lines.length;
    const emergencyText = lines.slice(emergencyStartIndex, emergencyEndIndex)
        .filter((line) => !/^[0-9OIlSsBbGgQq+().\s-]+$/.test(line))
        .join(' ');
    const emergencyName = emergencyAnchor >= 0
        ? emergencyText
            .replace(phoneCandidatePattern, ' ')
            .replace(/in\s+case\s+of\s+emergency|please\s+notify|emergency\s+contact|contact\s+person|notify/gi, ' ')
            .replace(/contact(?:\s*(?:no\.?|number))?|mobile|phone/gi, ' ')
            .replace(/[|:]/g, ' ')
            .replace(/\b[OIlSsBbGgQq]\b/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
        : '';
    const emergencyPhone = [...new Set(emergencyPhones.map(({ phone }) => phone))][0] || '';

    return {
        contact,
        emergency: [emergencyName, emergencyPhone].filter(Boolean).join(' · '),
    };
}
