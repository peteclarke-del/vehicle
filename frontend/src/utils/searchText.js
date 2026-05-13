const appendValue = (bucket, value) => {
  if (value === null || value === undefined) {
    return;
  }

  if (Array.isArray(value)) {
    value.forEach((entry) => appendValue(bucket, entry));
    return;
  }

  if (typeof value === 'object') {
    Object.values(value).forEach((entry) => appendValue(bucket, entry));
    return;
  }

  bucket.push(String(value));
};

export const buildSearchText = (...values) => {
  const bucket = [];
  values.forEach((value) => appendValue(bucket, value));
  return bucket.join(' ').toLowerCase();
};

export const matchesFreeText = (needle, ...values) => {
  const normalized = (needle || '').trim().toLowerCase();
  if (!normalized) {
    return true;
  }

  return buildSearchText(...values).includes(normalized);
};
