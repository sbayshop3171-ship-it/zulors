export function getRotatedImageDimensions(width, height, rotationDegrees = 0) {
	const radians = (rotationDegrees * Math.PI) / 180;
	const cosine = Math.abs(Math.cos(radians)) < 1e-10 ? 0 : Math.abs(Math.cos(radians));
	const sine = Math.abs(Math.sin(radians)) < 1e-10 ? 0 : Math.abs(Math.sin(radians));

	return {
		width: width * cosine + height * sine,
		height: width * sine + height * cosine,
	};
}

export function getCropZoomRange(imageWidth, imageHeight, frameWidth, frameHeight, rotationDegrees = 0) {
	if(! imageWidth || ! imageHeight || ! frameWidth || ! frameHeight) {
		return { minZoom: 1, coverZoom: 1 };
	}

	const baseScale = Math.max(frameWidth / imageWidth, frameHeight / imageHeight);
	const rotated = getRotatedImageDimensions(imageWidth, imageHeight, rotationDegrees);
	const containScale = Math.min(frameWidth / rotated.width, frameHeight / rotated.height);
	const radians = (rotationDegrees * Math.PI) / 180;
	const cosine = Math.abs(Math.cos(radians)) < 1e-10 ? 0 : Math.abs(Math.cos(radians));
	const sine = Math.abs(Math.sin(radians)) < 1e-10 ? 0 : Math.abs(Math.sin(radians));
	const requiredImageWidth = frameWidth * cosine + frameHeight * sine;
	const requiredImageHeight = frameWidth * sine + frameHeight * cosine;
	const coverScale = Math.max(requiredImageWidth / imageWidth, requiredImageHeight / imageHeight);

	return {
		minZoom: Math.min(1, containScale / baseScale),
		coverZoom: Math.max(1, coverScale / baseScale),
	};
}

export function clampCropOffset(offsetX, offsetY, imageWidth, imageHeight, frameWidth, frameHeight, rotationDegrees = 0) {
	const radians = (rotationDegrees * Math.PI) / 180;
	const cosine = Math.abs(Math.cos(radians)) < 1e-10 ? 0 : Math.abs(Math.cos(radians));
	const sine = Math.abs(Math.sin(radians)) < 1e-10 ? 0 : Math.abs(Math.sin(radians));
	const imageSpaceOffsetX = offsetX * cosine + offsetY * sine;
	const imageSpaceOffsetY = -offsetX * sine + offsetY * cosine;
	const imageSpaceLimitX = (imageWidth - (frameWidth * cosine + frameHeight * sine)) / 2;
	const imageSpaceLimitY = (imageHeight - (frameWidth * sine + frameHeight * cosine)) / 2;
	let clampedX;
	let clampedY;

	if(imageSpaceLimitX >= 0 && imageSpaceLimitY >= 0) {
		const localX = Math.min(imageSpaceLimitX, Math.max(-imageSpaceLimitX, imageSpaceOffsetX));
		const localY = Math.min(imageSpaceLimitY, Math.max(-imageSpaceLimitY, imageSpaceOffsetY));

		clampedX = localX * cosine - localY * sine;
		clampedY = localX * sine + localY * cosine;
	}
	else {
		// When zoomed out enough to show the full source with a white backdrop, keep
		// panning inside the rotated image's visible bounds.
		const rotated = getRotatedImageDimensions(imageWidth, imageHeight, rotationDegrees);
		const limitX = Math.abs(rotated.width - frameWidth) / 2;
		const limitY = Math.abs(rotated.height - frameHeight) / 2;
		clampedX = Math.min(limitX, Math.max(-limitX, offsetX));
		clampedY = Math.min(limitY, Math.max(-limitY, offsetY));
	}

	return {
		offsetX: clampedX === 0 ? 0 : clampedX,
		offsetY: clampedY === 0 ? 0 : clampedY,
	};
}
