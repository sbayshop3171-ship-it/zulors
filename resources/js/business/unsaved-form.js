export function businessFormValuesEqual(left, right) {
	return Array.isArray(left) && Array.isArray(right)
		? left.length === right.length && left.every((value, index) => value === right[index])
		: left === right;
}

export function hasBusinessFormChanges(fields, baselines) {
	for(const field of fields) {
		const baseline = baselines.get(field.componentId);

		if(baseline?.has(field.fieldKey)) {
			if(!businessFormValuesEqual(field.value, baseline.get(field.fieldKey))) return true;
		}
		else if(field.differsFromDefault) {
			return true;
		}
	}

	return false;
}
