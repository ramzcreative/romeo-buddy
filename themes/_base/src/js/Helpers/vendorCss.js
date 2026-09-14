// Vite's default CSS import injects an unlayered <style>, and unlayered CSS
// outranks every @layer — so vendor rules would beat our components. Import
// with ?inline and wrap it here instead.
export function injectVendorCss(css) {
	const style = document.createElement('style');
	style.textContent = `@layer vendor{${css}}`;
	document.head.appendChild(style);
}
