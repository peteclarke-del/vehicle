/**
 * Production-safe logger utility
 * Only logs in development mode, suppresses in production
 */

const isDevelopment = process.env.NODE_ENV === 'development';

const logger = {
  log: (...args) => {
    if (isDevelopment) {
      console.log(...args);
    }
  },

  info: (...args) => {
    if (isDevelopment) {
      console.info(...args);
    }
  },

  warn: (...args) => {
    if (isDevelopment) {
      console.warn(...args);
    }
  },

  error: (...args) => {
    // Always log errors, even in production
    console.error(...args);
  },

  debug: (...args) => {
    if (isDevelopment) {
      console.debug(...args);
    }
  },

  table: (...args) => {
    if (isDevelopment && console.table) {
      console.table(...args);
    }
  },

  group: (label) => {
    if (isDevelopment && console.group) {
      console.group(label);
    }
  },

  groupEnd: () => {
    if (isDevelopment && console.groupEnd) {
      console.groupEnd();
    }
  }
};

export default logger;
