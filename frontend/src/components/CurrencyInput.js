import React, { useState, useEffect } from 'react';

const CURRENCY_SYMBOLS = { GBP: '£', USD: '$', EUR: '€' };

function toFixed2(num) {
  return Math.trunc(num * 100) / 100;
}

function formatNumber(num, decimalPlaces = 2) {
  if (num === null || num === undefined || num === '') return '';
  return num.toLocaleString('en-GB', {
    minimumFractionDigits: decimalPlaces,
    maximumFractionDigits: decimalPlaces,
  });
}

function parseRaw(str, allowNegative = false) {
  if (!str && str !== 0) return null;
  // Strip currency symbols and commas, allow negative sign
  const cleaned = String(str).replace(/[^0-9.\-]/g, '');
  const num = parseFloat(cleaned);
  if (isNaN(num)) return null;
  return allowNegative ? num : Math.abs(num);
}

function CurrencyInput({
  label,
  currency = 'GBP',
  value,
  onChange,
  allowNegative = false,
  min,
  max,
  required = false,
  disabled = false,
  helperText,
  error,
  decimalPlaces = 2,
}) {
  const symbol = CURRENCY_SYMBOLS[currency] ?? currency;

  const initDisplay = () => {
    if (value === 0) return formatNumber(0, decimalPlaces);
    if (value != null && value !== '') return formatNumber(Number(value), decimalPlaces);
    return '';
  };

  const [displayValue, setDisplayValue] = useState(initDisplay);
  const [focused, setFocused] = useState(false);
  const [validationError, setValidationError] = useState(null);
  const inputRef = React.useRef(null);
  const onChangeRef = React.useRef(onChange);
  onChangeRef.current = onChange;

  // Use capture-phase native listener to catch change events even when value unchanged
  React.useEffect(() => {
    const input = inputRef.current;
    if (!input) return;
    const handler = (e) => {
      const raw = e.target.value;
      const cleaned = raw.replace(/[^0-9.\-]/g, '');
      if ((cleaned === '' || cleaned === '-') && onChangeRef.current) {
        onChangeRef.current(null);
      }
    };
    input.addEventListener('change', handler, true);
    return () => input.removeEventListener('change', handler, true);
  }, []);

  useEffect(() => {
    if (!focused) {
      setDisplayValue(initDisplay());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  const handleChange = (e) => {
    const raw = e.target.value;

    // Strip non-numeric (except decimal and optional minus)
    const cleaned = raw.replace(/[^0-9.\-]/g, '');
    if (cleaned === '' || cleaned === '-') {
      setDisplayValue(raw);
      if (onChange) onChange(null);
      setValidationError(null);
      return;
    }

    let num = parseFloat(cleaned);
    if (isNaN(num)) {
      setDisplayValue(raw);
      return;
    }

    if (!allowNegative) num = Math.abs(num);

    // Limit decimal places
    const factor = Math.pow(10, decimalPlaces);
    num = Math.trunc(num * factor) / factor;

    let errMsg = null;
    if (min !== undefined && num < min) errMsg = `Must be at least ${min}`;
    if (max !== undefined && num > max) errMsg = `Must not exceed ${max}`;
    setValidationError(errMsg);

    // Format the display value with commas when a complete number is entered
    const hasDecimalPoint = cleaned.endsWith('.');
    setDisplayValue(hasDecimalPoint ? raw : formatNumber(num, decimalPlaces));

    if (onChange) onChange(num);
  };

  const handleFocus = () => {
    setFocused(true);
    // Show raw number without formatting for easy editing
    const num = parseRaw(displayValue, allowNegative);
    setDisplayValue(num !== null ? String(num) : '');
  };

  const handleBlur = () => {
    setFocused(false);
    const num = parseRaw(displayValue, allowNegative);
    setDisplayValue(num !== null ? formatNumber(num, decimalPlaces) : '');
  };

  const handlePaste = (e) => {
    const pasted = e.clipboardData.getData('text');
    const num = parseRaw(pasted, allowNegative);
    if (num !== null) {
      e.preventDefault();
      const display = focused ? String(num) : formatNumber(num, decimalPlaces);
      setDisplayValue(display);
      onChange?.(num);
    }
  };

  const inputId = `currency-input-${label?.replace(/\s+/g, '-').toLowerCase()}`;

  return (
    <div>
      <label htmlFor={inputId}>{label}</label>
      <span>{symbol}</span>
      <input
        id={inputId}
        type="text"
        ref={inputRef}
        value={displayValue}
        onChange={handleChange}
        onFocus={handleFocus}
        onBlur={handleBlur}
        onPaste={handlePaste}
        required={required}
        disabled={disabled}
        inputMode="decimal"
      />
      {(validationError || error) && <span>{validationError || error}</span>}
      {helperText && !validationError && !error && <span>{helperText}</span>}
    </div>
  );
}

export default CurrencyInput;
