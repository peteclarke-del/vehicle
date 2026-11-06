const {getDefaultConfig, mergeConfig} = require('@react-native/metro-config');

/**
 * Metro configuration
 * https://facebook.github.io/metro/docs/configuration
 *
 * @type {import('metro-config').MetroConfig}
 */
// Package "exports" resolution must stay enabled: RN 0.87's own packages
// (e.g. @react-native/asset-utils) are only resolvable through it.
const config = {};

module.exports = mergeConfig(getDefaultConfig(__dirname), config);
