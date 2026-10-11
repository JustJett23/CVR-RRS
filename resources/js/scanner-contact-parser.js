const phoneCandidatePattern = /(?:\+?[0-9OIlSsBbGgQq][0-9OIlSsBbGgQq\s().-]{8,18})/gi;

function normalizePhoneCandidate(value) {
    const compact = value
        .replace(/[Oo]/g, '0')
        .replace(/[Il|]/g, '1')
        .replace(/[Ss]/g, '5')
        .replace(/[Bb]/g, '8')
        .replace(/[GgQq]/g, '9')
        .replace(/[^0-9+]/g, '');
    const phone = compact.match(/(?:\+639\d{9}|09\d{9})/)?.[0] || '';

    return phone;
}

function phoneCandidates(value) {
    return [...value.matchAll(phoneCandidatePattern)]
        .map((match) => normalizePhoneCandidate(match[0]))
        .filter(Boolean);
}

export function extractBackContacts(text) {
    const lines = String(text || '').replace(/\r/g, '').split('\n').map((line) => line.trim()).filter(Boolean);
    const emergencyLineIndexes = lines.flatMap((line, index) => (
        /emergency|please\s+notify|in\s+case\s+of|contact\s+person|notify/i.test(line) ? [index] : []
    ));
    const emergencyLineIndexSet = new Set(emergencyLineIndexes);
    const allPhones = lines.flatMap((line, index) => (
        phoneCandidates(line).map((phone) => ({ phone, index }))
    ));
    const emergencyPhones = allPhones.filter(({ index }) => (
        emergencyLineIndexSet.has(index)
        || emergencyLineIndexes.some((emergencyIndex) => index > emergencyIndex && index <= emergencyIndex + 2)
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
    const emergencyName = emergencyAnchor >= 0
        ? lines.slice(emergencyStartIndex, emergencyEndIndex)
            .map((line) => line
                .replace(/in\s+case\s+of\s+emergency|please\s+notify|emergency\s+contact|contact\s+person|notify/gi, ' ')
                .replace(/contact(?:\s*(?:no\.?|number))?|mobile|phone/gi, ' ')
                .replace(phoneCandidatePattern, ' ')
                .replace(/[|:]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim())
            .find((line) => {
                const words = line.replace(/[^A-Za-z ]/g, ' ').split(/\s+/).filter(Boolean);
                return words.length >= 2
                    && words.join('').length >= 6
                    && !/validation|1st|2nd|sem|contact(?:\s*(?:no\.?|number))?|mobile|phone/i.test(line);
            }) || ''
        : '';
    const emergencyPhone = [...new Set(emergencyPhones.map(({ phone }) => phone))][0] || '';

    return {
        contact,
        emergency: [emergencyName, emergencyPhone].filter(Boolean).join(' · '),
    };
}
