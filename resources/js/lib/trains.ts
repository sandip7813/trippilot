type TrainLabelParts = {
    name?: string | null;
    number?: string | null;
};

export function formatTrainLabel(train: TrainLabelParts): string {
    const name = train.name?.trim() ?? '';
    const number = train.number?.trim() ?? '';

    if (name !== '' && number !== '') {
        return `${name} (${number})`;
    }

    return name || number || 'Unknown train';
}
