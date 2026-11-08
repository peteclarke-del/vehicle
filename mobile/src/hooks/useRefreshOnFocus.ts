import {useCallback, useRef} from 'react';
import {useFocusEffect} from '@react-navigation/native';

/**
 * Re-run a screen's loader whenever the screen comes back into focus.
 *
 * Tab and stack screens stay mounted, so a mount-only useEffect leaves them
 * showing stale data after an edit on another screen (or on the web app).
 * The first focus is skipped because the screen's own mount effect already
 * loads the data.
 */
export function useRefreshOnFocus(load: () => unknown): void {
  const firstFocus = useRef(true);
  useFocusEffect(
    useCallback(() => {
      if (firstFocus.current) {
        firstFocus.current = false;
        return;
      }
      load();
    }, [load]),
  );
}
