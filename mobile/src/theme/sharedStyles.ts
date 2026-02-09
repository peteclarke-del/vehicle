import {StyleSheet} from 'react-native';

/**
 * Shared styles used across form and list screens.
 * Eliminates dozens of duplicated StyleSheet definitions.
 */
export const formStyles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollView: {
    flex: 1,
  },
  content: {
    padding: 16,
  },
  sectionTitle: {
    marginTop: 16,
    marginBottom: 12,
  },
  input: {
    marginBottom: 12,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  halfInput: {
    flex: 1,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    marginBottom: 8,
  },
  saveButton: {
    marginTop: 24,
    paddingVertical: 6,
  },
  deleteButton: {
    marginTop: 12,
  },
  bottomPadding: {
    height: 24,
  },
});

export const listStyles = StyleSheet.create({
  container: {
    flex: 1,
  },
  listContent: {
    padding: 16,
  },
  fab: {
    position: 'absolute',
    right: 16,
    bottom: 16,
  },
  chip: {
    marginRight: 4,
  },
  searchbar: {
    margin: 16,
    marginBottom: 8,
  },
});

export const receiptStyles = StyleSheet.create({
  receiptButtons: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 16,
  },
  receiptButton: {
    flex: 1,
  },
  receiptPreview: {
    marginBottom: 16,
  },
  receiptImageContainer: {
    position: 'relative',
  },
  receiptImage: {
    width: '100%',
    height: 200,
    borderRadius: 8,
  },
  removeReceiptButton: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: 'white',
  },
});
