/**
 * Checks if the given subject is a string or not.
 *
 * @param subject - A subject to check.
 *
 * @return `true` if the subject is a string, or otherwise `false`.
 */
export function isString(data: unknown): data is string {
  return typeof data === 'string';
}

export function sanitizeString(str: string) {

  const map: Record<string, string> = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#x27;',
      "/": '&#x2F;',
  };
  const reg = /[&<>"'/]/ig;
  return str.replace(reg, (match) => map[match]);

  // Remove all characters except alphanumeric, common punctuation, and spaces
  //return str.replace(/[^a-zA-Z0-9 .,_-]/g, '').trim();
}
export function sanitizedStrings(rawStrings: any[]): any[] {
  return rawStrings.map(str => {
    // Trim whitespace
    let cleanedStr = str.trim();
    // Basic HTML escaping (for display in HTML)
    cleanedStr = cleanedStr.replace(/&/g, '&amp;')
                           .replace(/</g, '&lt;')
                           .replace(/>/g, '&gt;')
                           .replace(/"/g, '&quot;')
                           .replace(/'/g, '&#039;');
    return cleanedStr;
  });
}

export function isFloat(value: number) {
  return typeof value === 'number' && !Number.isInteger(value) && !Number.isNaN(value);
}

/**
 * Returns an element that matches the provided selector.
 *
 * @param parent   - A parent element to start searching from.
 * @param selector - A selector to query.
 *
 * @return A found element or `null`.
 */
export function query<E extends Element = Element>( parent: Element | Document, selector: string ): E | null {
  return parent && parent.querySelector( selector );
}
/**
 * Returns the specified attribute value.
 *
 * @param elm  - An element.
 * @param attr - An attribute to get.
 */
export function getAttribute( elm: Element, attr: string ): string | null {
  return elm.getAttribute( attr );
}
/**
 * Merges two objects into a new object, combining their properties.
 * If properties exist in both objects, the properties from the second object will overwrite those from the first.
 * @param obj1 The first object.
 * @param obj2 The second object.
 * @returns A new object containing the merged properties.
 */
export function mergeObjects<T extends object, U extends object, V extends object>(
  obj1: T,
  obj2: U,
  obj3?: V // obj3 is optional
): T & U & V {
  if (obj3) {
    return { ...obj1, ...obj2, ...obj3 } as T & U & V;
  } else {
    return { ...obj1, ...obj2 } as T & U & V;
  }
}

/**
 * Merges two arrays, concatenating them.
 * @param arr1 The first array.
 * @param arr2 The second array.
 * @returns A new array containing the elements of both input arrays.
 */
export function mergeArrays<T, U>(arr1: T[], arr2: U[]): (T | U)[] {
  return [...arr1, ...arr2];
}
/**
 * Throws an error if the provided condition is falsy.
 *
 * @param condition - If falsy, an error is thrown.
 * @param message   - Optional. A message to display.
 */
export function assert( condition: any, message?: string ): void {
  if ( ! condition ) {
    throw new Error( `[animation] ${ message || '' }` );
  }
}